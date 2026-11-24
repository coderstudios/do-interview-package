<?php

namespace CoderStudios\DoInterview\Tests;

use CoderStudios\DoInterview\DoInterviewServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /** Tests that need the spike switched off set this, then call refreshApplication(). */
    public static bool $spikeEnabled = true;

    protected function getPackageProviders($app): array
    {
        return [DoInterviewServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('do-interview.spike.enabled', static::$spikeEnabled);
        $app['config']->set('do-interview.spike.middleware', ['web', 'auth']);
        $app['config']->set('do-interview.providers.openai-live.api_key', 'sk-test-not-a-real-key');
    }
}
