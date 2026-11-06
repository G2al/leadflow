<?php

namespace App\Providers;

use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        FilamentTimezone::set(config('leadflow.display_timezone'));

        RateLimiter::for('embed-view', fn (Request $request): Limit => Limit::perMinute(120)->by($request->ip()));

        RateLimiter::for('embed-submit', fn (Request $request): array => [
            Limit::perMinute(10)->by('minute:'.$request->ip())->response($this->tooManyRequests(...)),
            Limit::perHour(60)->by('hour:'.$request->ip())->response($this->tooManyRequests(...)),
        ]);
    }

    private function tooManyRequests(): JsonResponse
    {
        return response()->json(['message' => 'Troppe richieste. Riprova tra qualche minuto.'], 429);
    }
}
