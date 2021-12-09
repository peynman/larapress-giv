<?php

namespace Larapress\Giv\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Redis;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\ProductCategory;
use Larapress\Giv\Services\GivApi\Client;
use Larapress\Giv\Services\GivApi\PaginatedResponse;
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

        $now = Carbon::now()->format(config('larapress.giv.datetime_format'));
        $this->setSyncTimestamps(array_merge($timestamps, [
            'categories' => $now,
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
            100,
            $timestamps['colors'] ?? null,
        );

        $now = Carbon::now()->format(config('larapress.giv.datetime_format'));
        $this->setSyncTimestamps(array_merge($timestamps, [
            'colors' => $now,
        ]));
    }

    /**
     * Undocumented function
     *
     * @param Product|number|string $id
     * @return void
     */
    public function syncProductById ($product) {
        if (!is_object($product)) {
            $product = Product::find($product);
        }

        $cats = $product->categories;
        $inner = null;
        foreach ($cats as $category) {
            if (Str::startsWith($category->name, 'giv-')) {
                if (is_null($inner) || $inner->id > $category->id) {
                    $inner = $category;
                }
            }
        }

        if (!is_null($inner)) {
            /** @var IProductRepository */
            $repo = app(IProductRepository::class);

            $code = Str::substr($inner->name, Str::length('giv-'));
            $catIds = array_merge([$inner->id], $repo->getProductCategoryAncestorIds($inner));
            $givCode = Str::substr($product->name, Str::length('giv-'));
            $this->client->traverseProducts(
                function (PaginatedResponse $response) use ($catIds, $givCode) {
                    foreach ($response->Value as $prod) {
                        if ($prod->ItemCode == $givCode) {
                            $this->syncProduct(
                                $prod->ItemCode,
                                $catIds,
                                PersianText::standard($prod->ItemName),
                                $prod->IsActive,
                                $prod->ItemParentID,
                                null,
                                true
                            );
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
                    function (PaginatedResponse $response) use ($catIds, $dontSyncImages) {
                        foreach ($response->Value as $prod) {
                            $this->syncProduct(
                                $prod->ItemCode,
                                $catIds,
                                PersianText::standard($prod->ItemName),
                                $prod->IsActive,
                                $prod->ItemParentID,
                                $dontSyncImages ? null : $$timestamps['products'] ?? null,
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


        $now = Carbon::now()->format(config('larapress.giv.datetime_format'));
        $this->setSyncTimestamps(array_merge($timestamps, [
            'products' => $now,
        ]));
    }

    /**
     * Undocumented function
     *
     * @param integer $itemCode
     * @param integer $catIds
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
        $stock = $this->client->getProductsStock($itemCode);
        $existingProd = Product::withTrashed()
            ->where('author_id', config('larapress.giv.author_id'))
            ->where('name', 'giv-' . $itemCode)
            ->first();

        $inventory = $this->syncProductStock($stock, $prodParentId);
        if (!$dontSyncImages) {
            $images = [];
            if (!is_null($existingProd)) {
                $images = $existingProd->data['types']['images']['slides'];
            }
        } else {
            $images = $this->syncProductImages($itemCode, $existingProd, $prodParentId, $lastDate);
        }

        $attrs = [
            'deleted_at' => $isActive ? null : Carbon::now(),
            'data' => [
                'title' => $title,
                'fixedPrice' => [
                    'amount' => floatval($stock->SellPrice) / 10,
                    'currency' => config('larapress.ecommerce.banking.currency.id'),
                ],
                'quantized' => true,
                'maxQuantity' => -1,
                'types' => [
                    'cellar' => [
                        'inventory' => $inventory,
                    ],
                    'images' => [
                        'slides' => $images,
                    ],
                ],
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

        $existingProd->types()->sync([1, 2, 3]);
        $existingProd->categories()->sync($catIds);
    }

    /**
     * Undocumented function
     *
     * @param ProductStock $stock
     * @param int|null $prodParentId
     * @return array
     */
    protected function syncProductStock(ProductStock $stock, $prodParentId) {
        $inventory = [];
        $colorIds = [];

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

        return $inventory;
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
                return isset($img['index']) && $img['index'] === $prodImage->ImageIndex;
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
    }
}
