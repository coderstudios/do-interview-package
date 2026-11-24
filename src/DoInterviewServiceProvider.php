<?php

namespace CoderStudios\DoInterview;

use CoderStudios\DoInterview\Contracts\VoiceProvider;
use CoderStudios\DoInterview\Voice\VoiceManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class DoInterviewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/do-interview.php', 'do-interview');

        $this->app->singleton(VoiceManager::class, fn ($app) => new VoiceManager($app));
        $this->app->bind(VoiceProvider::class, fn ($app) => $app->make(VoiceManager::class)->driver());
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'do-interview');

        $this->publishes([
            __DIR__.'/../config/do-interview.php' => config_path('do-interview.php'),
        ], 'do-interview-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/do-interview'),
        ], 'do-interview-views');

        if (config('do-interview.spike.enabled')) {
            // Every start opens a paid provider session, so starts are rate limited per user.
            RateLimiter::for('do-interview-spike', fn (Request $request) => Limit::perHour(max(1, (int) config('do-interview.spike.starts_per_hour', 10)))
                ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

            $this->loadRoutesFrom(__DIR__.'/../routes/spike.php');
        }
    }
}
