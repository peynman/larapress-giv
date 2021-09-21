<?php

namespace Larapress\Giv\Providers;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\ServiceProvider;

class PackageServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Bootstrap services.
     *
     * @param  BroadcastManager $broadcastManager
     * @return void
     */
    public function boot(BroadcastManager $broadcastManager)
    {
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'larapress');

        $this->publishes([
            __DIR__.'/../../config/giv.php' => config_path('larapress/giv.php'),
        ], ['config', 'larapress', 'larapress-giv']);
    }
}
