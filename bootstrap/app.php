<?php

use App\Http\Middleware\DenyFraming;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::group([], base_path('routes/embed.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->group('embed', [SubstituteBindings::class]);
        $middleware->web(append: [DenyFraming::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The public POST has no session to redirect with: errors are always JSON.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('embed/*') && $request->isMethod('POST'),
        );
    })->create();
