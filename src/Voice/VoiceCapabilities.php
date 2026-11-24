<?php

namespace CoderStudios\DoInterview\Voice;

final readonly class VoiceCapabilities
{
    /**
     * @param  list<string>  $languages  BCP 47 language tags the provider handles well
     * @param  string  $transport  How the browser connects: 'webrtc-relay' (SDP through our server) or 'token'
     * @param  int|null  $maxSeconds  The provider's own session limit, when it publishes one
     */
    public function __construct(
        public bool $fullDuplex,
        public array $languages,
        public string $transport,
        public ?int $maxSeconds = null,
    ) {}
}
