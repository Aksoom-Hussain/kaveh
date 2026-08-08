<?php

use Kaveh\Server\Http\Controllers\IngestController;
use Kaveh\Server\Http\Middleware\AuthenticateApiKey;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'throttle:kaveh-ingest', AuthenticateApiKey::class])
    ->prefix('api')
    ->group(function () {
        Route::post('/v1/ingest', IngestController::class)->name('kaveh.api.ingest');
    });
