<?php

namespace Larapress\Giv\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\ProductCategory;
use Larapress\Giv\Services\GivApi\Client;
use Larapress\Giv\Services\GivApi\PaginatedResponse;
use Larapress\Giv\Services\GivApi\ProductQOH;
use Larapress\Profiles\Models\FormEntry;
use Illuminate\Support\Str;
use Larapress\CRUD\Services\Persian\PersianText;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Services\Product\IProductRepository;
use Larapress\FileShare\Models\FileUpload;
use Larapress\FileShare\Services\FileUpload\IFileUploadService;
use Larapress\Profiles\IProfileUser;
use Larapress\Profiles\Models\Filter;
use Larapress\Giv\Services\GivApi\Category;
use Larapress\Giv\Services\GivApi\ProductStock;
use Larapress\Notifications\Models\SMSMessage;
use Larapress\Notifications\Services\SMSService\Jobs\SendSMS;

class GivSyncronizer
{
    protected $REDIS_KEY = 'giv_sycn_timestamps';

    /** @var Client */
    protected $client;
    /** @var IFileUploadService */
    protected $fileService;
    /** @var IProfileUser */
    protected $authorUser;

    public function __construct()
    {
        $this->client = new Client();
        $this->fileService = app(IFileUploadService::class);
        $class = config('larapress.crud.user.model');
        $this->authorUser = call_user_func([$class, 'find'], config('larapress.giv.author_id'));
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function resetSyncTimestamps()
    {
        Redis::del($this->REDIS_KEY);
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getSyncTimestamps()
    {
        $values = Redis::get($this->REDIS_KEY);
        if (!is_null($values)) {
            return json_decode($values, true);
        }

        return [];
    }

    /**
     * Undocumented function
     *
     * @param array $timestamps
     *
     * @return void
     */
    public function setSyncTimestamps($timestamps)
    {
        Redis::set($this->REDIS_KEY, json_encode($timestamps));
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function syncCategories()
    {
        $timestamps = $this->getSyncTimestamps();

        $internalCats = [];
        /** @var Category[] */
        $categoriesList = [];
        $this->client->traverseCategories(
            function (PaginatedResponse $response) use (&$categoriesList) {
                foreach ($response->Value as $cat) {
                    $categoriesList[] = $cat;
                }
            },
            null,
            100,
            $timestamps['categories'] ?? null,
        );

        usort($categoriesList, function (Category $a, Category $b) {
            return $a->CategoryCode <=> $b->CategoryCode;
        });

        foreach ($categoriesList as $cat) {
            if ($cat->CategoryCode <= 99) {
                $dbCat = ProductCategory::withTrashed()->updateOrCreate([
                    'author_id' => config('larapress.giv.author_id'),
                    'name' => 'giv-' . $cat->CategoryCode,
                ], [
                    'deleted_at' => $cat->CategoryIsActive ? null : Carbon::now(),
                    'parent_id' => null,
                    'data' => [
                        'title' => PersianText::standard($cat->CategoryName),
                        'order' => $cat->OrderIndex,
                        'showOnProductCard' => $cat->VirtualSaleActive,
                        'isFilterable' => $cat->VirtualSaleActive,
                        'showInFrontFilters' => false,
                        'queryFrontEnd' => false,
                        'giv' => [
                            'code' => $cat->CategoryCode,
                            'active' => $cat->CategoryIsActive,
                            'virtualSale' => $cat->VirtualSaleActive,
                        ],
                    ],
                ]);
                $internalCats[$cat->CategoryCode] = $dbCat->id;
            } else {
                $parentCode = floor($cat->CategoryCode / 100);
                $parent_id = $parentCode > 99 && isset($internalCats[$parentCode]) ? $internalCats[$parentCode] : null;
                $dbCat = ProductCategory::withTrashed()->updateOrCreate([
                    'author_id' => config('larapress.giv.author_id'),
                    'name' => 'giv-' . $cat->CategoryCode,
                ], [
                    'deleted_at' => $cat->CategoryIsActive ? null : Carbon::now(),
                    'parent_id' => $parent_id,
                    'data' => [
                        'title' => PersianText::standard($cat->CategoryName),
                        'order' => $cat->OrderIndex,
                        'showOnProductCard' => $cat->VirtualSaleActive,
                        'isFilterable' => $cat->VirtualSaleActive,
                        'showInFrontFilters' => $cat->VirtualSaleActive,
                        'queryFrontEnd' => $cat->VirtualSaleActive,
                        'giv' => [
                            'code' => $cat->CategoryCode,
                            'active' => $cat->CategoryIsActive,
                            'virtualSale' => $cat->VirtualSaleActive,
                        ],
                    ],
                ]);
                $internalCats[$cat->CategoryCode] = $dbCat->id;
            }
        }

        $now = Carbon::now(config('larapress.giv.datetime_timezone'))->format(config('larapress.giv.datetime_format'));
        $this->setSyncTimestamps(array_merge($timestamps, [
            'categories' => $now,
        ]));
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function syncInventory () {
        $timestamps = $this->getSyncTimestamps();
        $this->client->traverseInventroyItems(function (PaginatedResponse $response) {
            /** @var ProductQOH $qoh */
            foreach ($response->Value as $qoh) {
                $prodParentId = substr($qoh->ItemID, 0, 5);
                $prod = Product::withTrashed()->where('data->givItemParentID', $prodParentId)->first();
                if (!is_null($prod)) {
                    $itemCode = substr($prod->name, strlen('giv-'));
                    [$inventory, $stock] = $this->syncProductStock($itemCode, $prodParentId);

                    $data = $prod->data;
                    $data['types']['cellar']['inventory'] = $inventory;
                    $prod->update([
                        'data' => $data,
                    ]);
                }
            }
        }, 50, $timestamps['inventory'] ?? null);

        $now = Carbon::now(config('larapress.giv.datetime_timezone'))->format(config('larapress.giv.datetime_format'));
        $this->setSyncTimestamps(array_merge($timestamps, [
            'inventory' => $now,
        ]));
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function syncColors()
    {
        $timestamps = $this->getSyncTimestamps();
        $this->client->traverseColors(
            function (PaginatedResponse $response) {
                foreach ($response->Value as $color) {
                    Filter::updateOrCreate([
                        'author_id' => config('larapress.giv.author_id'),
                        'type' => 'giv-color',
                        'name' => 'id' . $color->ItemColorID,
                    ], [
                        'zorder' => $color->OrderIndex,
                        'data' => [
                            'hex' => $color->ColorHex,
                            'name' => PersianText::standard($color->ItemColorName),
                        ]
                    ]);
                }
            },
            500,
            null,
        );

        $now = Carbon::now(config('larapress.giv.datetime_timezone'))->format(config('larapress.giv.datetime_format'));
        $this->setSyncTimestamps(array_merge($timestamps, [
            'colors' => $now,
        ]));
    }

    /**
     * Undocumented function
     *
     * @param string $code
     * @param string $cat
     * @return void
     */
    public function syncProductByCode($itemCode, $cat, $dontSyncImages = false)
    {
        /** @var ProductCategory */
        $cat = ProductCategory::find($cat);

        /** @var IProductRepository */
        $repo = app(IProductRepository::class);

        if (Str::startsWith($cat->name, 'giv-')) {
            $code = Str::substr($cat->name, Str::length('giv-'));
            $catIds = array_merge([$cat->id], $repo->getProductCategoryAncestorIds($cat));
            $this->client->traverseProducts(
                function (PaginatedResponse $response) use ($catIds, $itemCode, $dontSyncImages) {
                    foreach ($response->Value as $prod) {
                        if ($prod->ItemCode == $itemCode) {
                            $this->syncProduct(
                                $prod->ItemCode,
                                $catIds,
                                PersianText::standard($prod->ItemName),
                                $this->isProductActive($prod),
                                $prod->ItemParentID,
                                null,
                                $dontSyncImages
                            );
                            return 'stop';
                        }
                    }
                },
                $code,
                null,
                50,
                null
            );
        }
    }

    /**
     * Undocumented function
     *
     * @param Product|number|string $id
     * @return void
     */
    public function syncProductById($product, $dontSyncImages = false)
    {
        if (!is_object($product)) {
            $product = Product::find($product);
        }

        $cats = $product->categories;
        $inner = null;
        foreach ($cats as $category) {
            if (Str::startsWith($category->name, 'giv-')) {
                if (is_null($inner) || $inner->id < $category->id) {
                    $inner = $category;
                }
            }
        }

        $givCode = Str::substr($product->name, Str::length('giv-'));
        $repo = app(IProductRepository::class);

        $syncProductCallback = function ($catIds) use ($givCode, $dontSyncImages) {
            return function (PaginatedResponse $response)
            use ($catIds, $givCode, $dontSyncImages) {
                foreach ($response->Value as $prod) {
                    if ($prod->ItemCode == $givCode) {
                        $this->syncProduct(
                            $prod->ItemCode,
                            $catIds,
                            PersianText::standard($prod->ItemName),
                            $this->isProductActive($prod),
                            $prod->ItemParentID,
                            null,
                            $dontSyncImages
                        );
                        return 'stop';
                    }
                }
            };
        };

        if (!is_null($inner)) {
            /** @var IProductRepository */
            $code = Str::substr($inner->name, Str::length('giv-'));
            $catIds = array_merge([$inner->id], $repo->getProductCategoryAncestorIds($inner));
            $this->client->traverseProducts(
                $syncProductCallback($catIds),
                $code,
                null,
                50,
                null
            );
        } else if (is_numeric($givCode)) {
            $this->client->traverseCategories(function (PaginatedResponse $response)
            use ($syncProductCallback, $repo) {
                foreach ($response->Value as $cat) {
                    $inner = ProductCategory::where('name', 'giv-' . $cat->CategoryCode)->first();
                    if (!is_null($inner)) {
                        $catIds = array_merge([$inner->id], $repo->getProductCategoryAncestorIds($inner));
                        $stop = $this->client->traverseProducts(
                            $syncProductCallback($catIds),
                            $cat->CategoryCode,
                            null,
                            50,
                            null
                        );
                        if ($stop === 'stop') {
                            return 'stop';
                        }
                    }
                }
            }, null, 100);
        }
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function syncProducts($dontSyncImages = false)
    {
        $timestamps = $this->getSyncTimestamps();

        /** @var ProductCategory[] */
        $cats = ProductCategory::query()
            ->with(['parent', 'parent.parent'])
            ->select('id', 'parent_id', 'name')
            ->get();

        /** @var IProductRepository */
        $repo = app(IProductRepository::class);

        foreach ($cats as $cat) {
            if (Str::startsWith($cat->name, 'giv-')) {
                $code = Str::substr($cat->name, Str::length('giv-'));
                $catIds = array_merge([$cat->id], $repo->getProductCategoryAncestorIds($cat));
                $this->client->traverseProducts(
                    function (PaginatedResponse $response) use ($catIds, $timestamps, $dontSyncImages) {
                        foreach ($response->Value as $prod) {
                            $this->syncProduct(
                                $prod->ItemCode,
                                $catIds,
                                PersianText::standard($prod->ItemName),
                                $this->isProductActive($prod),
                                $prod->ItemParentID,
                                $dontSyncImages ? null : $timestamps['products'] ?? null,
                                $dontSyncImages
                            );
                        }
                    },
                    $code,
                    null,
                    50,
                    $dontSyncImages ? null : $timestamps['products'] ?? null
                );
            }
        }


        if (!$dontSyncImages) {
            $now = Carbon::now(config('larapress.giv.datetime_timezone'))->format(config('larapress.giv.datetime_format'));
            $this->setSyncTimestamps(array_merge($timestamps, [
                'products' => $now,
            ]));
        }
    }

    /**
     * Undocumented function
     *
     * @param integer $itemCode
     * @param array $catIds
     * @return void
     */
    public function syncProduct(
        int $itemCode,
        array $catIds,
        string $title,
        bool $isActive,
        $prodParentId = null,
        $lastDate = null,
        $dontSyncImages = false
    ) {
        $existingProd = Product::withTrashed()
            ->where('author_id', config('larapress.giv.author_id'))
            ->where('name', 'giv-' . $itemCode)
            ->first();

        [$inventory, $stock] = $this->syncProductStock($itemCode, $prodParentId);
        if ($dontSyncImages) {
            $images = [];
            if (!is_null($existingProd)) {
                $images = $existingProd->data['types']['images']['slides'];
            }
        } else {
            $images = $this->syncProductImages($itemCode, $existingProd, $prodParentId, $lastDate);
        }

        $existingTypes = [];
        $existingTypesData = [];
        $existingCats = [];
        if (!is_null($existingProd)) {
            $existingTypes = $existingProd->types->pluck('id')->toArray();
            $existingTypesData = $existingProd->data['types'];
            $existingCats = $existingProd->categories->pluck('id')->toArray() ?? [];
            if (!is_null($existingTypesData)) {
                unset($existingTypesData['cellar']);
                unset($existingTypesData['images']);
            }
        }

        $attrs = [
            'deleted_at' => $isActive ? null : Carbon::now(),
            'data' => [
                'givItemParentID' => $prodParentId,
                'title' => $title,
                'fixedPrice' => [
                    'amount' => floatval($stock->SellPrice) / 10,
                    'currency' => config('larapress.ecommerce.banking.currency.id'),
                ],
                'quantized' => true,
                'maxQuantity' => -1,
                'types' => array_merge($existingTypesData, [
                    'cellar' => [
                        'inventory' => $inventory,
                    ],
                    'images' => [
                        'slides' => $images,
                    ],
                ]),
            ]
        ];

        /** @var Product */
        if (!is_null($existingProd)) {
            $existingProd->update($attrs);
        } else {
            $existingProd = Product::create(array_merge($attrs, [
                'author_id' => config('larapress.giv.author_id'),
                'name' => 'giv-' . $itemCode,
            ]));
        }

        $existingProd->types()->sync(
            array_unique(array_merge([1, 2, 3], $existingTypes), SORT_REGULAR)
        );
        $existingProd->categories()->sync(
            array_unique(array_merge($catIds, $existingCats), SORT_REGULAR)
        );
    }

    /**
     * Undocumented function
     *
     * @param ProductStock $stock
     * @param int|null $prodParentId
     * @return array
     */
    protected function syncProductStock($itemCode, $prodParentId)
    {
        $inventory = [];
        $colorIds = [];

        $stock = $this->client->getProductsStock($itemCode);
        if ($stock?->Table?->TableData) {
            foreach ($stock->Table->TableData as $data) {
                foreach ($data->Items as $dataItem) {
                    if (is_null($prodParentId) && !is_null($dataItem->ItemParentID)) {
                        $prodParentId = $dataItem->ItemParentID;
                    }

                    $colorIds[] = 'id' . $dataItem->ItemColorID;
                    $inventory[] = [
                        'stock' => $dataItem->QOH,
                        'color' => $dataItem->ItemColorID,
                        'name' => $dataItem->ItemColorName,
                        'ref' => $dataItem->ItemColorID,
                        'size' => $dataItem->ItemSizeDesc,
                        'itemId' => $dataItem->ItemID,
                        'parentId' => $dataItem->ItemParentID,
                    ];
                }
            }
        }

        /** @var Collection */
        $colorFilters = Filter::query()->whereIn('name', $colorIds)->where('type', 'giv-color')->get();
        $colorFilterIds = $colorFilters->keyBy('name');
        foreach ($inventory as &$item) {
            /** @var Filter */
            $colorFilter = $colorFilterIds->get('id' . $item['ref']);
            if (!is_null($colorFilter) && isset($colorFilter->data['hex']) && !empty($colorFilter->data['hex'])) {
                $item['color'] = '#' . $colorFilter->data['hex'];
            }
        }

        return [$inventory, $stock];
    }

    /**
     * Undocumented function
     *
     * @param int $itemCode
     * @param Product|null $existingProd
     * @param  $prodParentId
     * @param [type] $lastDate
     * @return array
     */
    protected function syncProductImages(
        $itemCode,
        $existingProd,
        $prodParentId,
        $lastDate
    ) {
        $images = [];
        $existingImages = Collection::make($existingProd?->data['types']['images']['slides'] ?? []);
        $prodImages = $this->client->getProductImages($prodParentId, $lastDate);
        foreach ($prodImages as $prodImage) {
            $existingImage = $existingImages->first(function ($img) use ($prodImage) {
                return isset($img['index']) && intval($img['index']) === $prodImage->ImageIndex;
            });

            if ($prodImage->IsActive) {
                if (is_null($existingImage)) {
                    $localPath = $this->client->downloadImageFile($prodImage->ImagePath);
                    $fileUpload = $this->fileService->processLocalFile(
                        $this->authorUser,
                        $localPath,
                        trans('larapress::giv.giv_product_image_title', [
                            'itemCode' => $itemCode,
                            'index' => $prodImage->ImageIndex,
                        ]),
                        config('larapress.fileshare.default_public_disk'),
                        FileUpload::ACCESS_PUBLIC,
                        'product-images',
                        null,
                        [
                            'givItemCode' => $itemCode,
                        ],
                        true
                    );
                    // remove temp download
                    unlink($localPath);

                    $imgWidth = $fileUpload->data['dimentions']['width'] ?? config('larapress.giv.product_default_image_width');
                    $imgHeight = $fileUpload->data['dimentions']['height'] ?? config('larapress.giv.product_default_image_height');
                    $images[] = [
                        'image' => '/storage' . $fileUpload->path,
                        'width' => $imgWidth,
                        'height' => $imgHeight,
                        'ref' => $prodImage->ColorID,
                        'aspect' => floatval($imgWidth) / floatval($imgHeight),
                        'fileId' => $fileUpload->id,
                        'index' => $prodImage->ImageIndex,
                    ];
                } else {
                    $images[] = $existingImage;
                }
            }
        }

        return $images;
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @return void
     */
    public function syncUser(IECommerceUser $user)
    {
        $customer = $this->client->updateCustomer($user);
        $givForm = $user->giv_user_form;
        if (is_null($givForm)) {
            FormEntry::create([
                'user_id' => $user->id,
                'domain_id' => $user->getMembershipDomainId(),
                'form_id' => config('larapress.giv.giv_user_form_id'),
                'data' => [
                    'values' => [
                        'PersonID' => $customer->PersonID,
                    ],
                ]
            ]);
        } else {
            $givForm->update([
                'data' => [
                    'values' => [
                        'PersonID' => $customer->PersonID,
                    ],
                ]
            ]);
        }
    }

    /**
     * Undocumented function
     *
     * @param Cart $cart
     * @return void
     */
    public function syncCart(Cart $cart)
    {
        $this->syncUser($cart->customer);
        $cart->customer->load('giv_user_form');
        $this->client->updateOrder($cart);
        $this->sendCartSyncedSMSMessage($cart);
    }

    /**
     * Undocumented function
     *
     * @param Cart $cart
     * @return void
     */
    public function sendCartSyncedSMSMessage(Cart $cart)
    {
        if (is_null(config('larapress.giv.sms_gate_cart_sync'))) {
            return;
        }

        $firstname = $cart->customer->form_profile_default?->data['values']['firstname'] ?? null;
        $lastname = $cart->customer->form_profile_default?->data['values']['lastname'] ?? null;

        $message = trans('larapress::giv.sms.cart_synced', [
            'fullname' => $firstname . ' ' . $lastname,
        ]);
        $smsMessage = SMSMessage::create([
            'author_id' => config('larapress.giv.author_id'),
            'sms_gateway_id' => config('larapress.giv.sms_gate_cart_sync'),
            'from' => trans('larapress::giv.sms.from'),
            'to' => $cart->customer->phones[0]->number,
            'message' => $message,
            'flags' => 0,
            'status' => SMSMessage::STATUS_CREATED,
            'data' => [
                'desc' => 'giv-syncronizer',
                'cart_id' => $cart->id,
            ]
        ]);
        SendSMS::dispatch($smsMessage);
    }

    /**
     * Undocumented function
     *
     * @param [type] $prod
     * @return boolean
     */
    protected function isProductActive($prod) {
        return $prod->IsActive && $prod->VirtualSaleActive;
    }
}
