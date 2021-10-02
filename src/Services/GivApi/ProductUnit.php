<?php

namespace Larapress\Giv\Services\GivApi;

use Larapress\CRUD\Extend\CastableClassArray;

class ProductUnit extends CastableClassArray
{
    public $ItemUnitID;
    public $ItemUnitDesc;
    public $ItemHasSize;
    public $ItemHasColor;
    public $LastDate;

    public $TYPE_CASTS = [
        'ItemUnitID' => 'int',
        'ItemHasSize' => 'bool',
        'ItemHasColor' => 'bool',
        'DateChanged' => 'date',
    ];
}
