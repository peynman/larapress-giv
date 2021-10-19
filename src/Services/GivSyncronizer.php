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
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\Product;
use Larapress\FileShare\Models\FileUpload;
use Larapress\FileShare\Services\FileUpload\IFileUploadService;
use Larapress\Profiles\IProfileUser;
use Larapress\Profiles\Models\Filter;
use Larapress\Giv\Services\GivApi\Category;

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
            50,
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
                        'title' => $cat->CategoryName,
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
                        'title' => $cat->CategoryName,
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
                            'name' => $color->ItemColorName,
                        ]
                    ]);
                }
            },
            50,
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
     * @return void
     */
    public function syncProducts()
    {
        $timestamps = $this->getSyncTimestamps();

        /** @var ProductCategory[] */
        $cats = ProductCategory::query()->select('id', 'name')->get();

        foreach ($cats as $cat) {
            if (Str::startsWith($cat->name, 'giv-')) {
                $code = Str::substr($cat->name, Str::length('giv-'));
                $this->client->traverseProducts(
                    function (PaginatedResponse $response) use ($cat) {
                        foreach ($response->Value as $prod) {
                            $this->syncProduct(
                                $prod->ItemCode,
                                $cat->id,
                                $prod->ItemName,
                                $prod->IsActive,
                                $prod->ItemParentID,
                                $timestamps['products'] ?? null
                            );
                        }
                    },
                    $code,
                    null,
                    10,
                    $timestamps['products'] ?? null
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
     * @param integer $catId
     * @return void
     */
    public function syncProduct(
        int $itemCode,
        int $catId,
        string $title,
        bool $isActive,
        $prodParentId = null,
        $lastDate = null
    ) {
        $stock = $this->client->getProductsStock($itemCode);
        $inventory = [];
        $images = [];

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

        $existingProd = Product::query()->where('author_id', config('larapress.giv.author_id'))->where('name', 'giv-' . $itemCode)->first();
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
                    $images[] = [
                        'image' => '/storage' . $fileUpload->path,
                        'width' => $fileUpload->data['dimentions']['width'] ?? config('larapress.giv.product_default_image_width'),
                        'width' => $fileUpload->data['dimentions']['height'] ?? config('larapress.giv.product_default_image_height'),
                        'ref' => $prodImage->ColorID,
                        'fileId' => $fileUpload->id,
                        'index' => $prodImage->ImageIndex,
                    ];
                } else {
                    $images[] = $existingImage;
                }
            }
        }

        $attrs = [
            'deleted_at' => $isActive ? null : Carbon::now(),
            'data' => [
                'title' => $title,
                'fixedPrice' => [
                    'amount' => $stock->SellPrice,
                    'currency' => config('larapress.ecommerce.banking.currency.id'),
                ],
                'quantized' => true,
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
        if (!is_null($catId)) {
            $existingProd->categories()->sync([$catId]);
        }
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

    }
}
