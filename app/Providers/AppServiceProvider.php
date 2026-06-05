<?php

namespace App\Providers;

use App\Services\Transcript\SupadataTranscriptProvider;
use App\Services\Transcript\TranscriptProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            TranscriptProvider::class,
            SupadataTranscriptProvider::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('ori', fn () => Limit::perMinute(30));
        RateLimiter::for('youtube', fn () => Limit::perMinute(30));
    }
}
