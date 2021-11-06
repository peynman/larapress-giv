<?php

namespace Larapress\Giv\Services\GivApi;

use Larapress\CRUD\Extend\CastableClassArray;

class Order extends CastableClassArray
{
    public $OrderID;
    public $PersonID;
    public $SourceID;
    public $No;
    public $CreditUsed;
    public $CouponCode;
    public $PostalCode;
    public $TotalQuantity;
    public $TotalPrice;
    public $TotalDiscount;
    public $PackingCost;
    public $TransferCost;
    public $PostRefCode;
    public $RecieverName;
    public $ReceiverProvinceID;
    public $ReceiverCity;
    public $ReceiverAddress;
    public $ReceiverTel;
    public $ReceiverMobile;
    public $ReceiverPostalCode;
    public $PaymentBank;
    public $PaymentType;
    public $PaymentStatus;
    public $PaymentBankRefCode;
    public $DateCreated;
    public $DateChanged;
    public $Date;
    public $EffectiveDate;
    public $Description;

    public $TYPE_CASTS = [
        'OrderID' => 'number',
        'PersonID' => 'number',
        'SourceID' => 'number',
        'No' => 'number',
        'CreditUsed' => 'number',
        'TotalQuantity' => 'number',
        'TotalPrice' => 'float',
        'TotalDiscount' => 'float',
        'PackingCost' => 'flaot',
        'TransferCost' => 'float',
        'ReceiverProvinceID' => 'number',
        'DateCreated' => 'date',
        'DateChanged' => 'date',
        'Date' => 'number',
        'EffectiveDate' => 'number',
    ];
}
