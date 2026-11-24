# DoInterview package

Researched, voice-based practice interviews with written debriefs, as a Laravel 13 package. It installs into DoRevision (host 1) and the standalone site, `dointerviews` (host 2). The design is in `control-center-a/build.md`.

**Status: M0 voice spike.** It's a five-minute interview from a fixed brief, used to judge whether GPT-Live's voice quality is good enough to continue. Research, briefs, debriefs, credits and accounts arrive in M1 and M2.

## What's here

| Part | Where |
|---|---|
| Voice contract | `src/Contracts/VoiceProvider.php` |
| GPT-Live driver (OpenAI `gpt-live-1.1`, voice `vesper`) | `src/Voice/Drivers/OpenAiLiveDriver.php` |
| Fake driver for tests and offline work | `src/Voice/Drivers/FakeVoiceDriver.php` |
| Driver selection from the pipeline profile | `src/Voice/VoiceManager.php`, `config/do-interview.php` |
| `InterviewBrief` and its GPT-Live renderer | `src/Brief/` |
| The spike: page, session route, fixed brief | `src/Http/Controllers/SpikeController.php`, `resources/views/spike.blade.php`, `resources/briefs/spike-apprenticeship.json` |

How a session starts: the browser makes a WebRTC offer and posts it to our server. The server forwards it to `POST /v1/live/sessions` with the API key and returns OpenAI's answer, then the browser talks to OpenAI directly. The browser never holds a provider key.

## Try the spike locally

```sh
cp .env.example .env     # then set APP_KEY (command in the file) and OPENAI_API_KEY
composer serve           # then open http://localhost:8000/interview-spike
```

- Set a monthly spending cap on the OpenAI project before adding the key. Each start costs about $0.05 a minute, up to about $0.25 for the five-minute limit.
- Use headphones, so the interviewer doesn't hear itself.
- With `DO_INTERVIEW_VOICE_DRIVER=fake`, the whole page runs without spending anything, up to the point where audio would start.
- The local server runs the spike without login. Inside DoRevision it sits behind `auth`, which is the default.

**What to judge (the M0 question):** Does the interviewer sound natural? Does it handle pauses and interruptions? Does it follow the brief in order with sensible follow-ups? Does the British voice suit UK students? Download the transcript afterwards to check what it heard.

## Install into DoRevision

This hasn't been done yet. In DoRevision:

```jsonc
// composer.json
"repositories": [{ "type": "path", "url": "../do-interview-package", "options": { "symlink": true } }]
```

```sh
composer require coderstudios/do-interview:@dev
# .env
DO_INTERVIEW_SPIKE=true
OPENAI_API_KEY=...
```

The spike is then at `/interview-spike`, behind DoRevision's login. It's limited to 10 session starts an hour per user, and it registers no routes unless `DO_INTERVIEW_SPIKE=true`.

## Checks

```sh
composer test   # Pint, Larastan (level 6) and Pest, as in DoRevision
```

## Known gaps

- **Not yet tried against the real API.** The HTTP shape follows OpenAI's GPT-Live guide (21 November 2026): a JSON body with `session` and `transport`, a response with `session.id` and `transport.sdp`. It has only been tested against faked responses.
- **No server-side hard stop yet.** The page closes the session at the time limit and cuts the connection 10 seconds later if OpenAI doesn't confirm. A modified browser could keep talking until OpenAI's own limit, which isn't published. Before real users, M2 needs the server to end sessions itself: GPT-Live's server controls are the place to look. Until then, the OpenAI project's spending cap and the per-user start limit are the backstops.
- **Opening line.** On connect, the page sends silent context asking the interviewer to begin. If it waits for the candidate instead, the page prompts them to say hello. Check which happens.
- **EU data residency** for `/v1/live/sessions` is unconfirmed.
