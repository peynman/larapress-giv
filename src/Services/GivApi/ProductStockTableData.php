<?php

namespace Larapress\Giv\Services\GivApi;

use Larapress\CRUD\Extend\CastableClassArray;

class ProductStockTableData extends CastableClassArray
{
    /** @var ProductStockTableDataItem[] */
    public $Items;

    public $TYPE_CASTS = [
        'Items' => 'array:' . \Larapress\Giv\Services\GivApi\ProductStockTableDataItem::class,
    ];
}
