<?php

namespace CoderStudios\DoInterview\Voice;

use CoderStudios\DoInterview\Brief\Renderers\GptLiveBriefRenderer;
use CoderStudios\DoInterview\Contracts\VoiceProvider;
use CoderStudios\DoInterview\Voice\Drivers\FakeVoiceDriver;
use CoderStudios\DoInterview\Voice\Drivers\OpenAiLiveDriver;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Manager;

/**
 * Resolves voice drivers by name. The default comes from the active pipeline profile.
 *
 * @method VoiceProvider driver(string|null $driver = null)
 */
final class VoiceManager extends Manager
{
    public function getDefaultDriver(): string
    {
        $profile = $this->config->get('do-interview.profile', 'default');

        return (string) $this->config->get("do-interview.profiles.{$profile}.voice.driver", 'openai-live');
    }

    protected function createOpenaiLiveDriver(): VoiceProvider
    {
        return new OpenAiLiveDriver(
            $this->container->make(Http::class),
            $this->container->make(GptLiveBriefRenderer::class),
            (array) $this->config->get('do-interview.providers.openai-live', []),
        );
    }

    protected function createFakeDriver(): VoiceProvider
    {
        return new FakeVoiceDriver;
    }
}
