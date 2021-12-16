<?php

namespace Larapress\Giv\Services;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\CRUD\Services\CRUD\CRUDController;
use Larapress\ECommerce\Models\Cart;
use Larapress\Giv\Services\GivSyncronizer;

/**
 *
 * @group Giv
 */
class GivController extends CRUDController
{
    public static function registerApiRoutes()
    {
        Route::post('giv/sync/{id}', '\\' . self::class . '@syncCart')
            ->name(config('larapress.ecommerce.routes.carts.name') . '.any.sync');
    }

    /**
     * Clone Product
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
}
