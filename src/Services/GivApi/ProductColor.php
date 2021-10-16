<?php

namespace Larapress\Giv\Services\GivApi;

use Larapress\CRUD\Extend\CastableClassArray;

class ProductColor extends CastableClassArray {
    public $ItemColorID;
    public $ItemColorName;
    public $ColorHex;
    public $OrderIndex;
    public $LastDate;

    public $TYPE_CASTS = [
        'ItemColorID' => 'int',
        'OrderIndex' => 'int',
        'LastDate' => 'carbon',
    ];
}
