<?php

namespace Larapress\Giv\Services\GivApi;

use Carbon\Carbon;
use Exception;
use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\IECommerceUser;
use Larapress\FileShare\Models\FileUpload;
use Illuminate\Support\Str;

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
    public function traverseCategories(callable $callback, $level = '1', $limit = 10, string|null $lastDate = null)
    {
        $this->traverseRecords(
            $callback,
            '/api/itemcategoryl' . $level,
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
        $rndFilename = 'giv_'.Helpers::randomString().'.'.$mimeType;
        $localPath = storage_path('app/temp/'.$rndFilename);

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
                'SexCode' => $user->form_profile_default?->data['values']['gender'] ?? null,
                'IsActive' => true,
                'DateCreated' => $user->created_at->format(config('larapress.giv.datetime_format')),
            ],
            [
                'Value' => 'object:' . \Larapress\Giv\Services\GivApi\Customer::class,
            ]
        ));

        if ($response->Code === 1) {
            return $response->Value;
        }

        return null;
    }

    /**
     * Undocumented function
     *
     * @param string|null $date
     * @param integer $limit
     *
     * @return stdClass[]
     */
    public function getCustomersPaginated($limit = 10, string|null $lastdate = null)
    {
        return $this->callPaginatedMethod(
            '/api/customer',
            'GET',
            [
                'count' => $limit,
                'lastdate' => $lastdate,
            ],
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
    public function traverseCustomers(callable $callback, $limit = 10, string|null $lastDate = null)
    {
        $this->traverseRecords(
            $callback,
            '/api/customer',
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

        $paginated = $this->callPaginatedMethod($url, $method, $params, $casts);
        $items = $paginated->Value;
        $total += $paginated->ResultSize;

        $callback($paginated);

        while ($total < $paginated->TotalCount && $paginated->ResultSize > 0) {
            $paginated = $this->callPaginatedMethod($url, $method, array_merge($params, [
                'lastdate' => $paginated->Value[$paginated->ResultSize - 1]->LastDate,
            ]), $casts);

            if ($paginated->ResultSize > 0) {
                $callback($paginated);
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
            $paginated = $this->callPaginatedMethod('/api/itemcategoryl1', 'GET', [
                ...$params,
                'lastdate' => $paginated->Value[$paginated->ResultSize - 1]->LastDate,
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
            var_dump($result);
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
