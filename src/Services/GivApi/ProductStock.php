<?php

namespace Larapress\Giv\Services\GivApi;

use Larapress\CRUD\Extend\CastableClassArray;

class ProductStock extends CastableClassArray {
    public $ItemCode;
    public $ItemName;
    public $ItemColorID;
    public $ItemSize;
    public $TotalQOH;
    public $SellPrice;
    public $ImagePath;
    /** @var ProductStockTable */
    public $Table;

    public $TYPE_CASTS = [
        'ItemCode' => 'int',
        'ItemColorID' => 'int',
        'ItemSize' => 'int',
        'TotalQOH' => 'float',
        'SellPrice' => 'float',
        'Table' => 'object:' . \Larapress\Giv\Services\GivApi\ProductStockTable::class,
    ];
}
