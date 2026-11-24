<?php

namespace CoderStudios\DoInterview;

use CoderStudios\DoInterview\Content\ContentRepository;
use CoderStudios\DoInterview\Contracts\VoiceProvider;
use CoderStudios\DoInterview\Voice\VoiceManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Factory as ViewFactory;

final class DoInterviewServiceProvider extends ServiceProvider
{
    public const EXAMPLES = __DIR__.'/../resources/examples';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/do-interview.php', 'do-interview');

        // The examples path is added here, not in the config file: a host's published copy of the
        // config lives in its own config/ folder, where __DIR__ would point somewhere else.
        $this->app->singleton(ContentRepository::class, fn ($app) => new ContentRepository([
            ...array_values((array) $app['config']->get('do-interview.content.paths', [])),
            ...($app['config']->get('do-interview.content.examples', true) ? [self::EXAMPLES] : []),
        ]));
        $this->app->singleton(VoiceManager::class, fn ($app) => new VoiceManager($app));
        $this->app->bind(VoiceProvider::class, fn ($app) => $app->make(VoiceManager::class)->driver());
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'do-interview');

        // Prompt templates resolve from the host's content paths first (ContentRepository).
        $this->callAfterResolving('view', function (ViewFactory $view) {
            $view->addNamespace('do-interview-prompts', $this->app->make(ContentRepository::class)->promptPaths());
        });

        $this->publishes([
            __DIR__.'/../config/do-interview.php' => config_path('do-interview.php'),
        ], 'do-interview-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/do-interview'),
        ], 'do-interview-views');

        // A starting point for the host's own scenarios and prompts, to edit and keep private.
        $this->publishes([
            __DIR__.'/../resources/examples' => resource_path('do-interview'),
        ], 'do-interview-content');

        if (config('do-interview.spike.enabled')) {
            // Every start opens a paid provider session, so starts are rate limited per user.
            RateLimiter::for('do-interview-spike', fn (Request $request) => Limit::perHour(max(1, (int) config('do-interview.spike.starts_per_hour', 10)))
                ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

            $this->loadRoutesFrom(__DIR__.'/../routes/spike.php');
        }
    }
}
