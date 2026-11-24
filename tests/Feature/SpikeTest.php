<?php

use CoderStudios\DoInterview\Contracts\VoiceProvider;
use CoderStudios\DoInterview\Tests\TestCase;
use CoderStudios\DoInterview\Voice\Drivers\FakeVoiceDriver;
use CoderStudios\DoInterview\Voice\VoiceManager;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Http;

const SPIKE_OFFER = "v=0\r\no=- 1 2 IN IP4 127.0.0.1\r\ns=-\r\n";

function signedIn(): User
{
    $user = new User;
    $user->forceFill(['id' => 42]);

    return $user;
}

beforeEach(function () {
    $this->fake = new FakeVoiceDriver;
    $this->app->instance(VoiceProvider::class, $this->fake);
    // The spike route is web middleware; these tests check behaviour, not CSRF.
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

afterEach(function () {
    TestCase::$spikeEnabled = true;
});

it('requires login to open the spike page', function () {
    $this->getJson('/interview-spike')->assertUnauthorized();
});

it('requires login to start a paid session', function () {
    $this->postJson('/interview-spike/session', ['sdp' => SPIKE_OFFER])->assertUnauthorized();

    expect($this->fake->started)->toBeEmpty();
});

it('shows the spike page with the brief and data channel', function () {
    $this->actingAs(signedIn())
        ->get('/interview-spike')
        ->assertOk()
        ->assertSee('Practice interview')
        ->assertSee('Level 3 Engineering Technician apprenticeship')
        ->assertSee('oai-events', false)
        ->assertSee('csrf-token', false);
});

it('starts a session and returns only what the browser needs', function () {
    $this->actingAs(signedIn())
        ->postJson('/interview-spike/session', ['sdp' => SPIKE_OFFER])
        ->assertCreated()
        ->assertJsonPath('session_id', 'fake_1')
        ->assertJsonPath('transport', 'webrtc')
        ->assertJsonPath('max_seconds', 300)
        ->assertJsonMissingPath('api_key');

    expect($this->fake->started)->toHaveCount(1)
        ->and($this->fake->started[0]['options']->sdpOffer)->toBe(SPIKE_OFFER)
        ->and($this->fake->started[0]['options']->maxSeconds)->toBe(300);
});

it('caps the session at the configured maximum', function () {
    config()->set('do-interview.spike.max_seconds', 120);

    $this->actingAs(signedIn())->postJson('/interview-spike/session', ['sdp' => SPIKE_OFFER])->assertJsonPath('max_seconds', 120);
});

it('rejects a missing or malformed offer', function (array $body) {
    $this->actingAs(signedIn())->postJson('/interview-spike/session', $body)->assertUnprocessable()->assertJsonValidationErrors('sdp');

    expect($this->fake->started)->toBeEmpty();
})->with([
    'missing' => [[]],
    'not SDP' => [['sdp' => 'hello']],
    'too long' => [['sdp' => 'v=0'.str_repeat('a', 20001)]],
]);

it('rate limits session starts per user', function () {
    config()->set('do-interview.spike.starts_per_hour', 2);
    $user = signedIn();

    $this->actingAs($user)->postJson('/interview-spike/session', ['sdp' => SPIKE_OFFER])->assertCreated();
    $this->actingAs($user)->postJson('/interview-spike/session', ['sdp' => SPIKE_OFFER])->assertCreated();
    $this->actingAs($user)->postJson('/interview-spike/session', ['sdp' => SPIKE_OFFER])->assertTooManyRequests();

    expect($this->fake->started)->toHaveCount(2);
});

it('returns a safe error when the provider fails', function () {
    $this->app->forgetInstance(VoiceProvider::class);
    $this->app->offsetUnset(VoiceProvider::class);
    $this->app->bind(VoiceProvider::class, fn ($app) => $app->make(VoiceManager::class)->driver('openai-live'));
    Http::fake(['*' => Http::response(['error' => ['message' => 'Internal detail']], 500)]);

    $this->actingAs(signedIn())
        ->postJson('/interview-spike/session', ['sdp' => SPIKE_OFFER])
        ->assertStatus(502)
        ->assertJsonPath('message', 'The voice service could not start a session. Try again in a moment.')
        ->assertDontSee('Internal detail');
});

it('registers no routes when the spike is off', function () {
    TestCase::$spikeEnabled = false;
    $this->refreshApplication();

    $this->actingAs(signedIn())->get('/interview-spike')->assertNotFound();
    $this->actingAs(signedIn())->postJson('/interview-spike/session', ['sdp' => SPIKE_OFFER])->assertNotFound();
});
