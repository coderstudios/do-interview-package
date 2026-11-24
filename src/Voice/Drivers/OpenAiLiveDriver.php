<?php

namespace CoderStudios\DoInterview\Voice\Drivers;

use CoderStudios\DoInterview\Brief\InterviewBrief;
use CoderStudios\DoInterview\Brief\Renderers\GptLiveBriefRenderer;
use CoderStudios\DoInterview\Contracts\VoiceProvider;
use CoderStudios\DoInterview\Support\Money;
use CoderStudios\DoInterview\Voice\ClientSession;
use CoderStudios\DoInterview\Voice\SessionOptions;
use CoderStudios\DoInterview\Voice\Transcript;
use CoderStudios\DoInterview\Voice\VoiceCapabilities;
use CoderStudios\DoInterview\Voice\VoiceProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;

/**
 * OpenAI GPT-Live over WebRTC (developers.openai.com/api/docs/guides/live).
 *
 * The browser builds an SDP offer and sends it to our server; this driver posts it to
 * POST /v1/live/sessions with the project API key and hands the SDP answer back. The browser
 * then talks to OpenAI directly over WebRTC, with JSON events on the "oai-events" data channel.
 */
final class OpenAiLiveDriver implements VoiceProvider
{
    /**
     * @param  array{api_key?: string|null, base_url?: string, model?: string, voice?: string, timeout?: int, price_per_minute_usd?: float}  $config
     */
    public function __construct(
        private readonly Http $http,
        private readonly GptLiveBriefRenderer $renderer,
        private readonly array $config,
    ) {}

    public function name(): string
    {
        return 'openai-live';
    }

    public function capabilities(): VoiceCapabilities
    {
        // OpenAI publishes no session length limit; the app enforces the credit limit itself.
        return new VoiceCapabilities(fullDuplex: true, languages: ['en-GB', 'en'], transport: 'webrtc-relay');
    }

    public function startSession(InterviewBrief $brief, SessionOptions $options): ClientSession
    {
        $key = $this->config['api_key'] ?? null;
        if (! is_string($key) || $key === '') {
            throw VoiceProviderException::missingCredentials($this->name());
        }
        if ($options->sdpOffer === null || $options->sdpOffer === '') {
            throw VoiceProviderException::missingOffer($this->name());
        }

        $payload = [
            'session' => [
                'model' => $this->config['model'] ?? 'gpt-live-1.1',
                'instructions' => $this->renderer->render($brief),
                'audio' => ['output' => ['voice' => $options->voice ?? $this->config['voice'] ?? 'vesper']],
                // Nothing is stored at OpenAI. Recording mode (build.md 3a) is handled by the app.
                'store' => false,
            ],
            'transport' => ['type' => 'webrtc', 'sdp' => $options->sdpOffer],
        ];

        try {
            $response = $this->http
                ->withToken($key)
                ->acceptJson()
                ->timeout($this->config['timeout'] ?? 15)
                ->post(rtrim($this->config['base_url'] ?? 'https://api.openai.com/v1', '/').'/live/sessions', $payload);
        } catch (ConnectionException) {
            throw VoiceProviderException::requestFailed($this->name(), 0, 'Could not reach the provider.');
        }

        if ($response->failed()) {
            $detail = $response->json('error.message');

            throw VoiceProviderException::requestFailed($this->name(), $response->status(), is_string($detail) ? $detail : null);
        }

        $sessionId = $response->json('session.id');
        $answer = $response->json('transport.sdp');
        if (! is_string($sessionId) || ! is_string($answer) || $sessionId === '' || $answer === '') {
            throw VoiceProviderException::unexpectedResponse($this->name());
        }

        return new ClientSession(
            provider: $this->name(),
            providerSessionId: $sessionId,
            transport: 'webrtc',
            sdpAnswer: $answer,
            maxSeconds: $options->maxSeconds,
            clientConfig: ['data_channel' => 'oai-events'],
        );
    }

    public function fetchTranscript(string $providerSessionId): ?Transcript
    {
        // GPT-Live streams transcripts as data-channel events and has no transcript endpoint.
        return null;
    }

    public function estimateCost(int $seconds): Money
    {
        $perMinute = (float) ($this->config['price_per_minute_usd'] ?? 0.05);

        return Money::fromDecimal(max(0, $seconds) * $perMinute / 60);
    }
}
