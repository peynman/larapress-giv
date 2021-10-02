<?php

namespace Larapress\Giv\Services\GivApi;

use Larapress\CRUD\Extend\CastableClassArray;

class Category extends CastableClassArray
{
    public $ParentCategoryCode;
    public $CategoryCode;
    public $CategoryName;
    public $CategoryIsActive;
    public $VirtualSaleActive;
    public $LastDate;
    public $OrderIndex;

    public $TYPE_CASTS = [
        'ParentCategoryCode' => 'int',
        'CategoryCode' => 'int',
        'CategoryIsActive' => 'bool',
        'VirtualSaleActive' => 'bool',
        'OrderIndex' => 'int',
        'LastDate' => 'carbon',
    ];
}
