<?php

return [

    /*
    | The pipeline profile in use. A profile names one driver, model and effort per stage
    | (build.md section 4). Switching a model is an environment change; switching a vendor
    | is a new driver plus a profile entry.
    */
    'profile' => env('DO_INTERVIEW_PROFILE', 'default'),

    'profiles' => [
        'default' => [
            // The research, brief and debrief drivers arrive with M1.
            'research' => ['driver' => 'claude', 'model' => env('DO_INTERVIEW_RESEARCH_MODEL', 'claude-sonnet-5-5'), 'effort' => env('DO_INTERVIEW_RESEARCH_EFFORT', 'medium')],
            'brief' => ['driver' => 'claude', 'model' => env('DO_INTERVIEW_BRIEF_MODEL', 'claude-opus-5-5'), 'effort' => env('DO_INTERVIEW_BRIEF_EFFORT', 'medium')],
            'voice' => ['driver' => env('DO_INTERVIEW_VOICE_DRIVER', 'openai-live')],
            'debrief' => ['driver' => 'claude', 'model' => env('DO_INTERVIEW_DEBRIEF_MODEL', 'claude-opus-5-5'), 'effort' => env('DO_INTERVIEW_DEBRIEF_EFFORT', 'high')],
        ],
    ],

    'providers' => [
        'openai-live' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('DO_INTERVIEW_VOICE_MODEL', 'gpt-live-1.1'),
            // The only British voice in GPT-Live's voice table (November 2026).
            'voice' => env('DO_INTERVIEW_VOICE', 'vesper'),
            'timeout' => 15,
            // List price, US dollars per active minute, billed per second. Used for estimates only.
            'price_per_minute_usd' => 0.05,
        ],
    ],

    /*
    | The M0 voice spike: a five-minute interview from a fixed brief. Off unless switched on.
    | The page calls a paid API on every start, so keep it behind login (the default) in any
    | shared environment. DO_INTERVIEW_SPIKE_MIDDLEWARE takes a comma-separated list.
    */
    'spike' => [
        'enabled' => (bool) env('DO_INTERVIEW_SPIKE', false),
        'prefix' => env('DO_INTERVIEW_SPIKE_PREFIX', 'interview-spike'),
        'middleware' => array_values(array_filter(array_map('trim', explode(',', (string) env('DO_INTERVIEW_SPIKE_MIDDLEWARE', 'web,auth'))))),
        'max_seconds' => (int) env('DO_INTERVIEW_SPIKE_MAX_SECONDS', 300),
        'starts_per_hour' => (int) env('DO_INTERVIEW_SPIKE_STARTS_PER_HOUR', 10),
        'brief' => env('DO_INTERVIEW_SPIKE_BRIEF', __DIR__.'/../resources/briefs/spike-apprenticeship.json'),
    ],

];
