<?php

namespace CoderStudios\DoInterview\Voice;

final readonly class SessionOptions
{
    /**
     * @param  int  $maxSeconds  The length the credits allow; the session must not run past it
     * @param  string|null  $sdpOffer  The browser's WebRTC offer, for relay providers
     * @param  string|null  $voice  Overrides the provider's configured voice
     */
    public function __construct(
        public int $maxSeconds,
        public ?string $sdpOffer = null,
        public ?string $voice = null,
    ) {}
}
