<?php

namespace CoderStudios\DoInterview\Http\Controllers;

use CoderStudios\DoInterview\Brief\InterviewBrief;
use CoderStudios\DoInterview\Content\ContentRepository;
use CoderStudios\DoInterview\Contracts\VoiceProvider;
use CoderStudios\DoInterview\Voice\SessionOptions;
use CoderStudios\DoInterview\Voice\VoiceProviderException;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * M0 voice spike (build.md section 11): a short interview from a fixed brief, to judge
 * whether the voice quality is good enough to continue. Not the product's session flow.
 */
final class SpikeController
{
    public function __construct(private readonly ContentRepository $content) {}

    public function show(VoiceProvider $voice, ViewFactory $views): View
    {
        $brief = $this->brief();

        return $views->make('do-interview::spike', [
            'brief' => $brief,
            'provider' => $voice->name(),
            'voiceName' => config("do-interview.providers.{$voice->name()}.voice"),
            'maxSeconds' => $this->maxSeconds($brief),
            'pricePerMinute' => $voice->estimateCost(60)->toDecimal(),
        ]);
    }

    public function start(Request $request, VoiceProvider $voice): JsonResponse
    {
        // Read the offer from the raw body: the host app's TrimStrings middleware would strip the
        // final CRLF, and an SDP whose last line is unterminated can be rejected.
        $raw = json_decode($request->getContent(), true);
        $validated = Validator::make(['sdp' => is_array($raw) ? ($raw['sdp'] ?? null) : null], [
            'sdp' => ['required', 'string', 'max:20000', 'starts_with:v=0'],
        ])->validate();

        $brief = $this->brief();
        $maxSeconds = $this->maxSeconds($brief);

        try {
            $session = $voice->startSession($brief, new SessionOptions(maxSeconds: $maxSeconds, sdpOffer: $validated['sdp']));
        } catch (VoiceProviderException $e) {
            Log::warning('do-interview.spike.start_failed', ['provider' => $voice->name(), 'error' => $e->getMessage()]);

            return response()->json(['message' => 'The voice service could not start a session. Try again in a moment.'], 502);
        }

        // Cost tracking starts here; M1 moves this into provider_runs.
        Log::info('do-interview.spike.session_started', [
            'provider' => $session->provider,
            'session_id' => $session->providerSessionId,
            'user_id' => $request->user()?->getAuthIdentifier(),
            'max_seconds' => $maxSeconds,
            'max_cost_usd' => $voice->estimateCost($maxSeconds)->toDecimal(),
        ]);

        return response()->json($session->toBrowser(), 201);
    }

    private function brief(): InterviewBrief
    {
        return $this->content->scenario((string) config('do-interview.spike.scenario', 'sample-apprenticeship'));
    }

    private function maxSeconds(InterviewBrief $brief): int
    {
        return min((int) config('do-interview.spike.max_seconds', 300), $brief->durationMinutes * 60);
    }
}
