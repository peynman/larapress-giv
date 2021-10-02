<?php

namespace Larapress\Giv\Services\GivApi;

use Larapress\CRUD\Extend\CastableClassArray;

class ProductGroup extends CastableClassArray
{
    public $ItemGroupID;
    public $ItemGroupDesc;
    public $LastDate;

    public $TYPE_CASTS = [
        'ItemGroupID' => 'int',
        'LastDate' => 'carbon',
    ];
}
