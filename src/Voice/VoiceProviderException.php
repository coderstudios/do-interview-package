<?php

namespace CoderStudios\DoInterview\Voice;

use RuntimeException;

/**
 * A voice provider could not start or serve a session. Messages are safe to log: they never
 * include credentials, and they never include the brief or the user's words.
 */
class VoiceProviderException extends RuntimeException
{
    public static function missingCredentials(string $provider): self
    {
        return new self("The {$provider} voice provider has no API key configured.");
    }

    public static function missingOffer(string $provider): self
    {
        return new self("The {$provider} voice provider needs the browser's SDP offer.");
    }

    public static function requestFailed(string $provider, int $status, ?string $detail): self
    {
        return new self(trim("The {$provider} voice provider refused the session (HTTP {$status}). ".($detail ?? '')));
    }

    public static function unexpectedResponse(string $provider): self
    {
        return new self("The {$provider} voice provider returned a response without a session ID or SDP answer.");
    }
}
