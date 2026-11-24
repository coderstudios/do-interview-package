<?php

namespace CoderStudios\DoInterview\Voice\Drivers;

use CoderStudios\DoInterview\Brief\InterviewBrief;
use CoderStudios\DoInterview\Contracts\VoiceProvider;
use CoderStudios\DoInterview\Support\Money;
use CoderStudios\DoInterview\Voice\ClientSession;
use CoderStudios\DoInterview\Voice\SessionOptions;
use CoderStudios\DoInterview\Voice\Transcript;
use CoderStudios\DoInterview\Voice\VoiceCapabilities;

/**
 * A voice provider that calls nothing, for tests and offline development.
 */
final class FakeVoiceDriver implements VoiceProvider
{
    /** @var list<array{brief: InterviewBrief, options: SessionOptions}> */
    public array $started = [];

    public function name(): string
    {
        return 'fake';
    }

    public function capabilities(): VoiceCapabilities
    {
        return new VoiceCapabilities(fullDuplex: true, languages: ['en-GB'], transport: 'webrtc-relay');
    }

    public function startSession(InterviewBrief $brief, SessionOptions $options): ClientSession
    {
        $this->started[] = ['brief' => $brief, 'options' => $options];

        return new ClientSession(
            provider: $this->name(),
            providerSessionId: 'fake_'.count($this->started),
            transport: 'webrtc',
            sdpAnswer: "v=0\r\no=- 0 0 IN IP4 127.0.0.1\r\ns=fake\r\n",
            maxSeconds: $options->maxSeconds,
            clientConfig: ['data_channel' => 'oai-events'],
        );
    }

    public function fetchTranscript(string $providerSessionId): ?Transcript
    {
        return null;
    }

    public function estimateCost(int $seconds): Money
    {
        return new Money(0);
    }
}
