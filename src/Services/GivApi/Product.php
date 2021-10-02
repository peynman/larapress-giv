<?php

namespace Larapress\Giv\Services\GivApi;

use Larapress\CRUD\Extend\CastableClassArray;

class Product extends CastableClassArray
{
    public $ItemParentID;
    public $ItemCode;
    public $ItemName;
    public $ItemGroup;
    public $ItemCategory;
    public $ItemUnit;
    public $ItemCurrentSelPrice;
    public $ItemSpec1;
    public $ItemSpec2;
    public $ItemSpec3;
    public $ItemSpec4;
    public $ItemSpec5;
    public $ItemSpec6;
    public $ItemSpec7;
    public $ItemSpec8;
    public $ItemSpec9;
    public $ItemSpec10;
    public $DisabledColorIDs;
    public $IsActive;
    public $VirtualSaleActive;
    public $DateCreated;
    public $LastDate;
    public $ItemPacks;

    public $TYPE_CASTS = [
        'ItemParentID' => 'int',
        'ItemCode' => 'int',
        'ItemName' => '',
        'ItemGroup' => 'object:' . \Larapress\Giv\Services\GivApi\ProductGroup::class,
        'ItemCategory' => 'object:' . \Larapress\Giv\Services\GivApi\Category::class,
        'ItemUnit' => 'object:' . \Larapress\Giv\Services\GivApi\ProductUnit::class,
        'ItemCurrentSelPrice' => 'float',
        'IsActive' => 'bool',
        'VirtualSaleActive' => 'bool',
        'DateCreated' => 'carbon',
        'LastDate' => 'carbon',
    ];
}
