<?php

namespace Larapress\Giv\Services\GivApi;

use Larapress\CRUD\Extend\CastableClassArray;

class ProductQOH extends CastableClassArray
{
    public $ItemID;
    public $ItemQuantityOnHand;
    public $IsActive;
    public $LastDate;

    public $TYPE_CASTS = [
        'ItemID' => 'int',
        'IsActive' => 'bool',
        'ItemQuantityOnHand' => 'float',
        'LastDate' => 'date',
    ];
}
