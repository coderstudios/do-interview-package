<?php

namespace CoderStudios\DoInterview\Contracts;

use CoderStudios\DoInterview\Brief\InterviewBrief;
use CoderStudios\DoInterview\Support\Money;
use CoderStudios\DoInterview\Voice\ClientSession;
use CoderStudios\DoInterview\Voice\SessionOptions;
use CoderStudios\DoInterview\Voice\Transcript;
use CoderStudios\DoInterview\Voice\VoiceCapabilities;
use CoderStudios\DoInterview\Voice\VoiceProviderException;

/**
 * A voice back end that conducts an interview from a brief (build.md section 4).
 * No controller, job or view names a vendor; they all depend on this contract.
 */
interface VoiceProvider
{
    public function name(): string;

    public function capabilities(): VoiceCapabilities;

    /**
     * Returns what the browser needs to connect. For providers that relay WebRTC through our
     * server, the options carry the browser's SDP offer and the result carries the SDP answer,
     * so the browser never holds a provider credential.
     *
     * @throws VoiceProviderException
     */
    public function startSession(InterviewBrief $brief, SessionOptions $options): ClientSession;

    /** Null when the provider only streams transcripts live; the browser then posts them back. */
    public function fetchTranscript(string $providerSessionId): ?Transcript;

    public function estimateCost(int $seconds): Money;
}
