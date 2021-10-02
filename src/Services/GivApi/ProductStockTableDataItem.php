<?php

namespace Larapress\Giv\Services\GivApi;

use Larapress\CRUD\Extend\CastableClassArray;

class ProductStockTableDataItem extends CastableClassArray {
    public $ItemParentID;
    public $ItemID;
    public $ItemColorID;
    public $ItemColorName;
    public $ItemSize;
    public $ItemSizeDesc;
    public $QOH;

    public $TYPE_CASTS = [
        'ItemParentID' => 'int',
        'ItemID' => 'int',
        'ItemColorID' => 'int',
        'ItemSize' => 'int',
        'QOH' => 'float',
    ];
}
