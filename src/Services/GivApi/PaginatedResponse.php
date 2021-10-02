<?php

namespace Larapress\Giv\Services\GivApi;

use Larapress\CRUD\Extend\CastableClassArray;

class PaginatedResponse extends CastableClassArray
{
    public $Code;
    public $Message;
    public $PageIndex;
    public $PageSize;
    public $TotalCount;
    public $ResultSize;
    public $LastDatetime;
    public $Value;

    public $TYPE_CASTS = [
        'Code' => 'int',
        'PageIndex' => 'int',
        'PageSize' => 'int',
        'TotalCount' => 'int',
        'ResultSize' => 'int',
        'LastDatetime' => 'carbon',
    ];
}
