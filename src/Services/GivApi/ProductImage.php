<?php

namespace Larapress\Giv\Services\GivApi;

use Larapress\CRUD\Extend\CastableClassArray;

class ProductImage extends CastableClassArray {
    public $ItemParentID;
    public $ImageIndex;
    public $ColorID;
    public $ImageDesc;
    public $IsActive;
    public $ImagePath;
    public $LastDate;
    public $ColorImage;

    public $TYPE_CASTS = [
        'ItemParentID' => 'int',
        'ImageIndex' => 'int',
        'ColorID' => 'int',
        'IsActive' => 'bool',
        'LastDate' => 'carbon',
        'ColorImage' => 'bool',
    ];
}
