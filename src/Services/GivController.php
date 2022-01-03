<?php

namespace Larapress\Giv\Services;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\CRUD\Services\CRUD\CRUDController;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\Product;
use Larapress\Giv\Services\GivSyncronizer;
use Illuminate\Support\Str;

/**
 *
 * @group Giv
 */
class GivController extends CRUDController
{
    public static function registerApiRoutes()
    {
        Route::post('giv/sync/cart/{id}', '\\' . self::class . '@syncCart')
            ->name(config('larapress.ecommerce.routes.carts.name') . '.any.sync');

        Route::post('giv/sync/product/{id}', '\\' . self::class . '@syncProduct')
            ->name(config('larapress.ecommerce.routes.products.name') . '.any.sync');
    }

    /**
     *
     * @return Response
     */
    public function syncCart($id)
    {
        $cart = Cart::find($id);
        if (!is_null($cart)) {
            return (new GivSyncronizer())->syncCart($cart);
        }

        throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
    }


    /**
     *
     * @return Response
     */
    public function syncProduct($id)
    {
        $product = Product::find($id);
        if (!is_null($product) && Str::startsWith($product->name, 'giv-')) {
            return (new GivSyncronizer())->syncProductById($product->id);
        }

        throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
    }
}
