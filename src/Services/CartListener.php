<?php

namespace Larapress\Giv\Services;

use Illuminate\Contracts\Queue\ShouldQueue;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Services\Cart\CartEvent;
use Larapress\Giv\Services\GivSyncronizer;

class CartListener implements ShouldQueue
{
    public function handle(CartEvent $event)
    {
        /** @var Cart */
        $cart = $event->getCart();

        if ($cart->status === Cart::STATUS_ACCESS_COMPLETE) {
            $synced = isset($cart->data['synced']) ? true : false;
            if (!$synced) {
                $syncer = new GivSyncronizer();
                $syncer->syncCart($cart);
            }
        }
    }
}
