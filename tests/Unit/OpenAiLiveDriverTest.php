<?php

use CoderStudios\DoInterview\Brief\Renderers\GptLiveBriefRenderer;
use CoderStudios\DoInterview\Contracts\VoiceProvider;
use CoderStudios\DoInterview\Voice\Drivers\FakeVoiceDriver;
use CoderStudios\DoInterview\Voice\Drivers\OpenAiLiveDriver;
use CoderStudios\DoInterview\Voice\SessionOptions;
use CoderStudios\DoInterview\Voice\VoiceManager;
use CoderStudios\DoInterview\Voice\VoiceProviderException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const OFFER = "v=0\r\no=- 1 2 IN IP4 127.0.0.1\r\ns=-\r\n";

function liveDriver(array $config = []): OpenAiLiveDriver
{
    return new OpenAiLiveDriver(app(Factory::class), app(GptLiveBriefRenderer::class), array_merge([
        'api_key' => 'sk-test-not-a-real-key',
        'base_url' => 'https://api.openai.com/v1',
        'model' => 'gpt-live-1.1',
        'voice' => 'vesper',
        'price_per_minute_usd' => 0.05,
    ], $config));
}

it('relays the SDP offer to GPT-Live and returns the answer', function () {
    Http::fake([
        'api.openai.com/v1/live/sessions' => Http::response([
            'session' => ['id' => 'live_123'],
            'transport' => ['type' => 'webrtc', 'sdp' => 'v=0 answer'],
        ], 201),
    ]);

    $session = liveDriver()->startSession(spikeBrief(), new SessionOptions(maxSeconds: 300, sdpOffer: OFFER));

    expect($session->providerSessionId)->toBe('live_123')
        ->and($session->sdpAnswer)->toBe('v=0 answer')
        ->and($session->toBrowser())->not->toHaveKey('api_key')
        ->and(json_encode($session->toBrowser()))->not->toContain('sk-test');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.openai.com/v1/live/sessions'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer sk-test-not-a-real-key')
            && $request['session']['model'] === 'gpt-live-1.1'
            && $request['session']['audio']['output']['voice'] === 'vesper'
            && $request['session']['store'] === false
            && str_contains($request['session']['instructions'], "I'm an AI interviewer")
            && $request['transport'] === ['type' => 'webrtc', 'sdp' => OFFER];
    });
});

it('lets the session options choose another voice', function () {
    Http::fake(['*' => Http::response(['session' => ['id' => 'live_1'], 'transport' => ['sdp' => 'v=0']], 201)]);

    liveDriver()->startSession(spikeBrief(), new SessionOptions(maxSeconds: 60, sdpOffer: OFFER, voice: 'willow'));

    Http::assertSent(fn (Request $r) => $r['session']['audio']['output']['voice'] === 'willow');
});

it('refuses to start without an API key, before calling the provider', function () {
    Http::fake();

    expect(fn () => liveDriver(['api_key' => ''])->startSession(spikeBrief(), new SessionOptions(60, OFFER)))
        ->toThrow(VoiceProviderException::class, 'no API key');

    Http::assertNothingSent();
});

it('refuses to start without the browser offer', function () {
    Http::fake();

    expect(fn () => liveDriver()->startSession(spikeBrief(), new SessionOptions(60)))
        ->toThrow(VoiceProviderException::class, 'SDP offer');

    Http::assertNothingSent();
});

it('reports a provider error without leaking the key', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid model']], 400)]);

    try {
        liveDriver()->startSession(spikeBrief(), new SessionOptions(60, OFFER));
        $this->fail('Expected an exception');
    } catch (VoiceProviderException $e) {
        expect($e->getMessage())->toContain('HTTP 400')->toContain('Invalid model')->not->toContain('sk-test');
    }
});

it('rejects a response without a session or answer', function () {
    Http::fake(['*' => Http::response(['session' => []], 201)]);

    liveDriver()->startSession(spikeBrief(), new SessionOptions(60, OFFER));
})->throws(VoiceProviderException::class, 'without a session ID');

it('estimates cost per second at the configured rate', function () {
    expect(liveDriver()->estimateCost(60)->micros)->toBe(50_000)
        ->and(liveDriver()->estimateCost(900)->format())->toBe('$0.75')
        ->and(liveDriver()->estimateCost(1)->micros)->toBe(833);
});

it('resolves the profile voice driver through the contract', function () {
    expect(app(VoiceProvider::class))->toBeInstanceOf(OpenAiLiveDriver::class);

    config()->set('do-interview.profiles.default.voice.driver', 'fake');

    expect(app(VoiceManager::class)->driver('fake'))->toBeInstanceOf(FakeVoiceDriver::class);
});
