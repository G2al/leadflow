<?php

use App\Http\Controllers\Embed\FormController;
use App\Http\Controllers\Embed\OrderController;
use App\Http\Controllers\Embed\ScriptController;
use App\Http\Middleware\EmbedFormHeaders;
use Illuminate\Support\Facades\Route;

/*
| Public, stateless routes used by external landing pages. They live outside the
| "web" group on purpose: no session, no cookies and no CSRF token, which would
| not survive in a third-party iframe anyway.
*/

Route::prefix('embed/offers')->name('embed.')->middleware('embed')->group(function (): void {
    Route::get('{offer:public_token}.js', ScriptController::class)
        ->middleware('throttle:embed-view')
        ->name('script');

    Route::get('{offer:public_token}/form', FormController::class)
        ->middleware(['throttle:embed-view', EmbedFormHeaders::class])
        ->name('form');

    Route::post('{offer:public_token}/orders', [OrderController::class, 'store'])
        ->middleware('throttle:embed-submit')
        ->name('orders.store');
});
