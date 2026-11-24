<?php

namespace CoderStudios\DoInterview\Voice;

/**
 * What the browser needs to join a voice session. Never carries a provider API key.
 */
final readonly class ClientSession
{
    /**
     * @param  array<string, mixed>  $clientConfig  Provider-specific settings the browser needs
     */
    public function __construct(
        public string $provider,
        public string $providerSessionId,
        public string $transport,
        public ?string $sdpAnswer = null,
        public int $maxSeconds = 0,
        public array $clientConfig = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toBrowser(): array
    {
        return [
            'provider' => $this->provider,
            'session_id' => $this->providerSessionId,
            'transport' => $this->transport,
            'sdp' => $this->sdpAnswer,
            'max_seconds' => $this->maxSeconds,
            'config' => $this->clientConfig,
        ];
    }
}
