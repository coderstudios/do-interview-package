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
    | Scenarios and prompt templates are the product's own content, so they live in the host
    | app, not in this public package. Each path is searched in order; the first match wins.
    | The package's own examples are always searched last, so a fresh install runs; set
    | `examples` to false to require the host's content. Layout under each path:
    |   scenarios/{name}.json
    |   prompts/{driver}/{template}.blade.php   e.g. prompts/gpt-live/interviewer.blade.php
    | DO_INTERVIEW_CONTENT_PATH points the host's path elsewhere, such as a private content repo.
    */
    'content' => [
        'paths' => [
            env('DO_INTERVIEW_CONTENT_PATH', resource_path('do-interview')),
        ],
        'examples' => (bool) env('DO_INTERVIEW_CONTENT_EXAMPLES', true),
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
        // A scenario name from the content paths above.
        'scenario' => env('DO_INTERVIEW_SPIKE_SCENARIO', 'sample-apprenticeship'),
    ],

];
