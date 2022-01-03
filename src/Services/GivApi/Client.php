<?php

namespace Larapress\Giv\Services\GivApi;

use Exception;
use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\IECommerceUser;
use Larapress\FileShare\Models\FileUpload;
use Illuminate\Support\Str;
use Larapress\ECommerce\Models\BankGatewayTransaction;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Services\Cart\Base\CartProductPurchaseDetails;
use Larapress\ECommerce\Services\Cart\ICart;
use Larapress\Profiles\Models\Filter;

class Client
{
    public function __construct()
    {
    }

    /**
     * Undocumented function
     *
     * @param string $method
     * @return string
     */
    public function getMethodUrl(string $method): string
    {
        return config('larapress.giv.base_url') . $method;
    }

    /**
     * Undocumented function
     *
     * @param callable $callback
     * @param string $level
     * @param int $limit
     * @param string|null $lastDate
     *
     * @return void
     */
    public function traverseCategories(callable $callback, $level = null, $limit = 10, string|null $lastDate = null)
    {
        $this->traverseRecords(
            $callback,
            is_null($level) ? '/api/itemcategory' : '/api/itemcategoryl' . $level,
            'GET',
            array_merge([
                'count' => $limit,
            ], !is_null($lastDate) ? ['lastdate' => $lastDate] : []),
            [
                'Value' => 'array:' . \Larapress\Giv\Services\GivApi\Category::class,
            ]
        );
    }

    /**
     * Undocumented function
     *
     * @param callable $callback
     * @param int|null $category
     * @param int|null $groupid
     * @param integer $limit
     * @param string|null $lastDate
     *
     * @return void
     */
    public function traverseProducts(callable $callback, $category = null, $groupid = null, $limit = 10, string|null $lastDate = null)
    {
        $params = [
            'count' => $limit,
        ];

        if (!is_null($category)) {
            $params['categorycode'] = $category;
        }
        if (!is_null($groupid)) {
            $params['groupid'] = $groupid;
        }
        if (!is_null($lastDate)) {
            $params['lastdate'] = $lastDate;
        }

        $this->traverseRecords(
            $callback,
            '/api/itemparent',
            'GET',
            $params,
            [
                'Value' => 'array:' . \Larapress\Giv\Services\GivApi\Product::class,
            ]
        );
    }

    /**
     * Undocumented function
     *
     * @param callable $callback
     * @param integer $limit
     * @param string|null|null $lastDate
     *
     * @return void
     */
    public function traverseColors(callable $callback, $limit = 10, string|null $lastDate = null)
    {
        $params = [
            'count' => $limit,
        ];

        if (!is_null($lastDate)) {
            $params['lastdate'] = $lastDate;
        }

        $this->traverseRecords(
            $callback,
            '/api/itemcolor',
            'GET',
            $params,
            [
                'Value' => 'array:' . \Larapress\Giv\Services\GivApi\ProductColor::class,
            ]
        );
    }

    /**
     * Undocumented function
     *
     * @param integer $itemId
     * @param integer $itemParentId
     * @param integer|null $lastDate
     *
     * @return void
     */
    public function checkQOH(int $itemId, int $itemParentId, int $lastDate = null)
    {
    }

    /**
     * Undocumented function
     *
     * @param int $prodId
     * @return ProductStock
     */
    public function getProductsStock($prodId)
    {
        return $this->callPaginatedMethod(
            '/api/itemqoh',
            'GET',
            [
                'inputcode' => $prodId,
            ],
            [
                'Value' => 'object:' . \Larapress\Giv\Services\GivApi\ProductStock::class,
            ]
        )->Value;
    }

    /**
     * Undocumented function
     *
     * @param int $prodParentId
     * @param string|null $lastDate
     *
     * @return ProductImage[]
     */
    public function getProductImages($prodParentId, $lastDate = null)
    {
        return $this->callPaginatedMethod(
            '/api/itemimage',
            'GET',
            array_merge([
                'parentId' => $prodParentId,
            ], !is_null($lastDate) ? ['lastdate' => $lastDate] : []),
            [
                'Value' => 'array:' . \Larapress\Giv\Services\GivApi\ProductImage::class,
            ]
        )->Value;
    }

    /**
     * Undocumented function
     *
     * @param string $url
     *
     * @return FileUpload
     */
    public function downloadImageFile($url)
    {
        $mimeType = null;
        if (Str::endsWith($url, '.jpg') || Str::endsWith($url, '.jpeg')) {
            $mimeType = 'jpg';
        } else if (Str::endsWith($url, '.png')) {
            $mimeType = 'png';
        }
        $rndFilename = 'giv_' . Helpers::randomString() . '.' . $mimeType;
        $localPath = storage_path('app/temp/' . $rndFilename);

        return $this->downloadFile($url, $localPath);
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @return Customer
     */
    public function updateCustomer(IECommerceUser $user)
    {
        $gender = $user->form_profile_default?->data['values']['gender'] ?? null;
        if (!is_null($gender)) {
            if ($gender == 0) {
                $gender = 'M';
            } else {
                $gender = 'F';
            }
        }
        $response = new PaginatedResponse($this->callMethod(
            '/api/customer',
            'POST',
            [
                'Mobile' => $user->phones[0]?->number ?? null,
                'PersonID' => $user->giv_user_form?->data['values']['PersonID'] ?? null,
                'FirstName' => $user->form_profile_default?->data['values']['firstname'] ?? null,
                'LastName' => $user->form_profile_default?->data['values']['lastname'] ?? null,
                'Address' => $user->addresses[0]?->address ?? null,
                'Email' => $user->emails[0]?->email ?? null,
                'ProvinceId' => $user->addresses[0]?->province_code ?? null,
                'City' => $user->addresses[0]?->city_code ?? null,
                'PostalCode' => $user->addresses[0]?->postal_code ?? null,
                'SexCode' => $gender,
                'Description' => 'Website User',
                'IsActive' => true,
                'DateCreated' => $user->created_at->format(config('larapress.giv.datetime_format')),
            ],
        ), [
            'Value' => 'object:' . \Larapress\Giv\Services\GivApi\Customer::class,
        ]);

        if ($response->Code === 1) {
            return $response->Value;
        }

        return null;
    }


    function gregorian_to_jalali($gy, $gm, $gd)
    {
        $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * ((int)($days / 12053)));
        $days %= 12053;
        $jy += 4 * ((int)($days / 1461));
        $days %= 1461;
        if ($days > 365) {
            $jy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + (int)($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + (int)(($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return array(
            str_pad($jy.'', 4, '0', STR_PAD_LEFT),
            str_pad($jm.'', 2, '0', STR_PAD_LEFT),
            str_pad($jd.'', 2, '0', STR_PAD_LEFT)
        );
    }


    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @return ICart
     */
    public function updateOrder(Cart $cart)
    {
        $customer = $cart->customer;
        $persionId = $customer->giv_user_form?->data['values']['PersonID'] ?? null;
        $address = $cart->getDeliveryAddress();
        $fullname = [
            $customer->form_profile_default?->data['values']['firstname'] ?? null,
            $customer->form_profile_default?->data['values']['lastname'] ?? null,
        ];

        if (is_null($persionId)) {
            throw new Exception("PersionID could not be found for cart $cart->id");
        }
        if (is_null($address)) {
            throw new Exception("Delivery address is not set for cart $cart->id");
        }

        [$province, $city] = Iran::getProvinceAndCityTitleByCode($address->province_code, $address->city_code);
        $ReceiverCity = $province . ' - '. $city;
        $orderId = isset($cart->data['givOrderId']) ? $cart->data['givOrderId'] : -1;

        /** @var BankGatewayTransaction */
        $transaction = BankGatewayTransaction::query()->where('cart_id', $cart->id)->first();

        $periodStart = $cart->getPeriodStart();
        $date = $periodStart->format(config('larapress.giv.datetime_format'));
        $farsiDate = implode('', $this->gregorian_to_jalali($periodStart->year, $periodStart->month, $periodStart->day));
        $discount = $cart->getGiftCodeUsage()?->amount * 10 ?? 0;

        $response = new PaginatedResponse($this->callMethod(
            '/api/order',
            'POST',
            [
                'OrderID' => $orderId,
                'PersonID' => $persionId,
                'No' => $cart->id,
                'SourceID' => $cart->id,
                'Description' => 'Website Cart: ' . $cart->getDeliveryAgentName(),
                'Type' => 'SALE',
                'PaymentStatus' => 'PAYMENT_STATUS_SUCCESSFUL',
                'PaymentType' => 'ONLINE',
                'CreditUsed' => 0,
                'PackingCost' => 0,
                'TransferCost' => $cart->getDeliveryPrice() * 10,
                'TotalPrice' => $cart->amount * 10,
                'TotalQuantity' => $cart->getTotalQuantity(),
                'TotalDiscount' => $discount,
                'ReceiverName' => implode(' ', $fullname),
                'PostRefCode' => $address->postal_code,
                'ReceiverPostalCode' => $address->postal_code,
                'ReceiverProvinceID' => $address->province_code,
                'ReceiverMobile' => $customer->phones[0]?->number ?? null,
                'ReceiverCity' => $ReceiverCity,
                'ReceiverAddress' => Helpers::safeLatinNumbers($address->address),
                'PaymentBankRefCode' => $transaction->reference_code,
                'PaymentBank' => $transaction->bank_gateway->name,
                'Date' => $farsiDate,
                'EffectiveDate' => $farsiDate,
                'DateCreated' => $date,
                'DateChanged' => $date,
            ],
        ), [
            'Value' => 'object:' . \Larapress\Giv\Services\GivApi\Order::class,
        ]);

        /** @var Order */
        $order = $response->Value;

        $products = $cart->products;
        $indexer = 1;
        foreach ($products as $product) {
            $details = new CartProductPurchaseDetails($product->pivot->data);

            $this->callMethod(
                '/api/orderrow',
                'POST',
                [
                    'OrderID' => $order->OrderID,
                    'RowID' => $indexer,
                    'ItemID' => $details->extra['itemId'],
                    'Quantity' => $details->quantity,
                    'Fee' => $details->amount * 10,
                    'RowDiscount' => $details->offAmount * 10,
                    'TotalDiscount' => $details->offAmount * 10,
                    'VatValue' => 0,
                    'DateCreated' => $date,
                    'DateChanged' => $date,
                ]
            );

            $indexer++;
        }

        $data = $cart->data;
        $data['givOrderId'] = $order->OrderID;
        $data['synced'] = true;
        $cart->update([
            'data' => $data,
        ]);

        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param callable $callback
     * @param integer $limit
     *
     * @return void
     */
    public function traverseCustomers(callable $callback, $limit = 10, string|null $lastDate = null)
    {
        $this->traverseRecords(
            $callback,
            '/api/customer',
            'GET',
            array_merge([
                'count' => $limit,
            ], !is_null($lastDate) ? ['lastdate' => $lastDate] : []),
            [
                'Value' => 'array:' . \Larapress\Giv\Services\GivApi\Customer::class,
            ]
        );
    }

    /**
     * Undocumented function
     *
     * @param callable $callback
     * @param integer $limit
     *
     * @return void
     */
    public function traverseInventroyItems(callable $callback, $limit = 10, string|null $lastDate = null)
    {
        $this->traverseRecords(
            $callback,
            '/api/quantityonhand',
            'GET',
            array_merge([
                'count' => $limit,
            ], !is_null($lastDate) ? ['lastdate' => $lastDate] : []),
            [
                'Value' => 'array:' . \Larapress\Giv\Services\GivApi\ProductQOH::class,
            ]
        );
    }

    /**
     * Undocumented function
     *
     * @param callable $callback
     * @param string $url
     * @param string $method
     * @param array $params
     * @param array $casts
     *
     * @return void
     */
    public function traverseRecords(callable $callback, string $url, $method = 'GET', $params = [], $casts = [])
    {
        $total = 0;
        $items = [];
        $limit = $params['count'] ?? 10;

        $paginated = $this->callPaginatedMethod($url, $method, $params, $casts);
        $items = $paginated->Value;
        $total += $paginated->ResultSize;

        $result = $callback($paginated);
        if ($result === 'stop') {
            return $items;
        }

        while ($total < $paginated->TotalCount && $paginated->ResultSize === $limit) {
            $paginated = $this->callPaginatedMethod($url, $method, array_merge($params, [
                'lastdate' => $paginated->Value[$paginated->ResultSize - 1]->LastDate->format(config('larapress.giv.datetime_format')),
            ]), $casts);

            if ($paginated->ResultSize > 0) {
                $result = $callback($paginated);
                if ($result === 'stop') {
                    break;
                }
            }

            $total += $paginated->ResultSize;
        }

        return $items;
    }

    /**
     * Undocumented function
     *
     * @param string $url
     * @param string $method
     * @param array $params
     *
     * @return stdClass[]
     */
    protected function getTotalRecords(string $url, $method = 'GET', $params = [], $casts = [])
    {
        $total = 0;
        $items = [];

        $paginated = $this->callPaginatedMethod($url, $method, $params, $casts);
        $items = $paginated->Value;
        $total += $paginated->ResultSize;
        while ($total < $paginated->TotalCount && $paginated->ResultSize > 0) {
            $paginated = $this->callPaginatedMethod($url, $method, [
                ...$params,
                'lastdate' => $paginated->Value[$paginated->ResultSize - 1]->LastDate->format(config('larapress.giv.datetime_format')),
            ], $casts);

            $items = array_merge($items, $paginated->Value);

            $total += $paginated->ResultSize;
        }

        return $items;
    }

    /**
     * Undocumented function
     *
     * @param string $url
     * @param string $method
     * @param array $params
     *
     * @return PaginatedResponse
     */
    protected function callPaginatedMethod(string $url, $method = 'GET', $params = [], $casts = []): PaginatedResponse
    {
        return new PaginatedResponse($this->callMethod($url, $method, $params), $casts);
    }

    /**
     * Undocumented function
     *
     * @param string $url
     * @param string $method
     * @param array $params
     *
     * @return stdClass
     */
    public function callMethod(string $url, $method = 'GET', $params = [])
    {
        $headers = [
            'WEB_TOKEN: ' . config('larapress.giv.token')
        ];
        if ($method === 'POST') {
            $ch = curl_init($this->getMethodUrl($url));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            $headers[] = 'Content-Type: application/json';
            var_dump($params);
        } else {
            $ch = curl_init($this->getMethodUrl($url) . '?' . http_build_query($params));
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $error = curl_errno($ch);

        if ($error) {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            throw new Exception("Curl error($error) [status:$http_code]: " . curl_error($ch));
        }

        curl_close($ch);

        try {
            var_dump($params);
            var_dump($result);
            var_dump('****************************************************************');

            return json_decode($result, true);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Undocumented function
     *
     * @param string $url
     * @param string $output
     * @param array $params
     *
     * @return boolean
     */
    public function downloadFile(string $url, string $output, $params = [])
    {
        $headers = [
            'WEB_TOKEN: ' . config('larapress.giv.token')
        ];
        $out = fopen($output, 'wb');
        $ch = curl_init($url . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FILE, $out);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60 * 5);

        curl_exec($ch);
        $error = curl_errno($ch);

        if ($error) {
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            throw new Exception("Curl error($error) [status:$http_code]: " . curl_error($ch));
        }

        curl_close($ch);
        fclose($out);

        return $output;
    }
}
