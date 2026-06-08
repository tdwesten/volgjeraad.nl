<?php

namespace App\Providers;

use App\Models\Municipality;
use App\Services\Transcript\SupadataTranscriptProvider;
use App\Services\Transcript\TranscriptProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;
use Spatie\OgImage\Facades\OgImage;

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

        $this->registerOgImages();
    }

    /**
     * Kies per route het juiste OG-image-template. De middleware injecteert het
     * resultaat (template + meta-tags) server-side in de HTML-response, dus de
     * Inertia/React-pagina's hoeven niets te weten van OG-images.
     */
    private function registerOgImages(): void
    {
        OgImage::fallbackUsing(function (Request $request): ?View {
            return match ($request->route()?->getName()) {
                'home' => view('og.home'),
                'municipality.show', 'municipality.archive' => ($municipality = $request->route('municipality')) instanceof Municipality
                    ? view('og.municipality', ['name' => $municipality->name])
                    : null,
                default => null,
            };
        });
    }
}
