<?php

namespace Larapress\Giv\Services\GivApi;

use Larapress\CRUD\Extend\CastableClassArray;

class ProductStockTable extends CastableClassArray {
    public $TableHead;
    public $TableTitle;
    /** @var ProductStockTableData[] */
    public $TableData;

    public $TYPE_CASTS = [
        'TableHead' => 'array',
        'TableTitle' => 'array',
        'TableData' => 'array:' . \Larapress\Giv\Services\GivApi\ProductStockTableData::class,
    ];
}
