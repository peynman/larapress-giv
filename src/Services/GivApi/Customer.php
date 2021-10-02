<?php

namespace Larapress\Giv\Services\GivApi;

use Larapress\CRUD\Extend\CastableClassArray;

class Customer extends CastableClassArray
{
    public $PersonID;
    public $FirstName;
    public $LastName;
    public $Address;
    public $Tel;
    public $Mobile;
    public $IsActive;
    public $Email;
    public $ProvinceId;
    public $City;
    public $PostalCode;
    public $SexCode;
    public $BirthDate;
    public $WeddingDate;
    public $HousbandBirthDate;
    public $ImportantDate;
    public $ImportantDateDesc;
    public $SpecialDiscountRate;
    public $GradeCode;
    public $ClassCode;
    public $SpecialDiscountAmount;
    public $SpecialDiscountType;
    public $VIPCode;
    public $VIPCardIssueDate;
    public $DateCreated;
    public $Occupation;
    public $HousbandOccupation;
    public $Nationality;
    public $NationalIDNo;
    public $LastDate;
    public $DateChanged;
    public $Description;

    public $TYPE_CASTS = [
        'PersonID' => 'int',
        'IsActive' => 'bool',
        'ProvinceId' => 'int',
        'PostalCode' => 'int',
        'SexCode' => 'int',
        'BirthDate' => 'date',
        'WeddingDate' => 'date',
        'HousbandBirthDate' => 'date',
        'ImportantDate' => 'date',
        'SpecialDiscountRate' => 'int',
        'GradeCode' => 'int',
        'ClassCode' => 'int',
        'SpecialDiscountAmount' => 'int',
        'VIPCode' => 'int',
        'VIPCardIssueDate' => 'date',
        'DateCreated' => 'date',
        'NationalIDNo' => 'int',
        'LastDate' => 'data',
        'DateChanged' => 'date',
    ];
}
