<?php

use Illuminate\Support\Facades\Route;
use Larapress\Giv\Services\GivController;

// api routes with protected access
Route::middleware(config('larapress.crud.middlewares'))
    ->prefix(config('larapress.crud.prefix'))
    ->group(function () {
        GivController::registerApiRoutes();
    });
