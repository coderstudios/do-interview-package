<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex">
<title>Practice interview (voice spike)</title>
<style>
:root { color-scheme: light dark; --page: #f9f9f7; --surface: #fcfcfb; --surface-2: #f2f1ee; --ink: #0b0b0b; --ink-2: #52514e; --muted: #898781; --ring: rgba(11,11,11,.09); --accent: #2a78d6; --good: #0ca30c; --warn: #8a5a00; --bad: #b22a2a; }
@media (prefers-color-scheme: dark) { :root { --page: #0d0d0d; --surface: #1a1a19; --surface-2: #232321; --ink: #f0efec; --ink-2: #c3c2b7; --muted: #898781; --ring: rgba(255,255,255,.08); --accent: #3987e5; --good: #0ca30c; --warn: #fab219; --bad: #e66767; } }
* { box-sizing: border-box; }
body { margin: 0; background: var(--page); color: var(--ink); font: 15px/1.55 system-ui, -apple-system, "Segoe UI", sans-serif; padding: 24px 16px 48px; }
main { max-width: 760px; margin: 0 auto; display: grid; gap: 16px; }
h1 { font-size: 22px; margin: 0; letter-spacing: -0.01em; }
h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin: 0 0 10px; }
.card { background: var(--surface); border-radius: 12px; box-shadow: inset 0 0 0 1px var(--ring); padding: 18px 20px; }
.sub { color: var(--ink-2); margin: 4px 0 0; }
.facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin: 0; }
.facts dt { font-size: 12px; color: var(--muted); } .facts dd { margin: 2px 0 0; font-weight: 600; }
.bar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
button { font: inherit; border: 0; border-radius: 10px; padding: 10px 18px; cursor: pointer; font-weight: 600; }
button.primary { background: var(--accent); color: #fff; }
button.secondary { background: var(--surface-2); color: var(--ink); box-shadow: inset 0 0 0 1px var(--ring); }
button:disabled { opacity: .45; cursor: default; }
.status { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; }
.dot { width: 10px; height: 10px; border-radius: 50%; background: var(--muted); }
.status.live .dot { background: var(--good); box-shadow: 0 0 0 4px color-mix(in srgb, var(--good) 25%, transparent); }
.status.error { color: var(--bad); } .status.error .dot { background: var(--bad); }
.timer { margin-left: auto; font-variant-numeric: tabular-nums; color: var(--ink-2); }
.hint { color: var(--warn); font-size: 13px; margin: 10px 0 0; min-height: 1em; }
.transcript { display: grid; gap: 10px; max-height: 420px; overflow: auto; }
.turn { padding: 10px 12px; border-radius: 10px; background: var(--surface-2); }
.turn.user { background: color-mix(in srgb, var(--accent) 12%, var(--surface)); }
.turn b { display: block; font-size: 12px; color: var(--muted); font-weight: 600; margin-bottom: 2px; }
.empty { color: var(--muted); }
details { font-size: 13px; color: var(--ink-2); }
details pre { white-space: pre-wrap; font: 12px ui-monospace, Menlo, monospace; max-height: 240px; overflow: auto; background: var(--surface-2); padding: 10px; border-radius: 8px; }
.disclosure { font-size: 13px; color: var(--ink-2); }
</style>
</head>
<body>
<main>
  <header>
    <h1>Practice interview</h1>
    <p class="sub">Voice spike (M0): {{ $brief->role }} at {{ $brief->organisation }}.</p>
  </header>

  <section class="card">
    <dl class="facts">
      <div><dt>Interviewer</dt><dd>{{ $brief->persona['name'] }}, {{ $brief->persona['style'] }}</dd></div>
      <div><dt>Questions</dt><dd>{{ count($brief->questions) }}</dd></div>
      <div><dt>Length limit</dt><dd>{{ intdiv($maxSeconds, 60) }} min {{ $maxSeconds % 60 ? ($maxSeconds % 60).' s' : '' }}</dd></div>
      <div><dt>Voice</dt><dd>{{ $provider }} · {{ $voiceName }}</dd></div>
    </dl>
    <p class="disclosure">This is an AI interviewer, for practice. It uses your microphone. Use headphones if you can, so the interviewer doesn't hear itself.</p>
  </section>

  <section class="card">
    <div class="bar">
      <button class="primary" id="start">Start interview</button>
      <button class="secondary" id="end" disabled>End</button>
      <span class="status" id="status"><span class="dot"></span><span id="status-text">Ready</span></span>
      <span class="timer" id="timer" aria-live="off">0:00 · $0.00</span>
    </div>
    <p class="hint" id="hint"></p>
  </section>

  <section class="card">
    <h2>Transcript</h2>
    <div class="transcript" id="transcript" aria-live="polite"><p class="empty">The conversation appears here as you speak.</p></div>
    <div class="bar" style="margin-top:12px"><button class="secondary" id="download" disabled>Download transcript</button></div>
  </section>

  <details class="card"><summary>Event log (types only, for debugging the spike)</summary><pre id="log"></pre></details>
</main>
<audio id="remote" autoplay></audio>

<script>
(() => {
  'use strict';
  const MAX_SECONDS = {{ (int) $maxSeconds }};
  const PRICE_PER_MINUTE = {{ (float) $pricePerMinute }};
  const SESSION_URL = @json(route('do-interview.spike.session'));
  const CSRF = document.querySelector('meta[name="csrf-token"]').content;

  const $ = (id) => document.getElementById(id);
  const startBtn = $('start'), endBtn = $('end'), statusEl = $('status'), statusText = $('status-text');
  const timerEl = $('timer'), hintEl = $('hint'), transcriptEl = $('transcript'), logEl = $('log'), downloadBtn = $('download');

  let pc = null, dc = null, mic = null, startedAt = 0, tick = null, hardStop = null, usageSeconds = 0, kickoffTimer = null;
  const turns = [];

  function setStatus(text, cls) { statusText.textContent = text; statusEl.className = 'status' + (cls ? ' ' + cls : ''); }
  function log(line) { logEl.textContent += `${new Date().toLocaleTimeString('en-GB')}  ${line}\n`; logEl.scrollTop = logEl.scrollHeight; }
  function fmt(s) { return `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`; }
  function send(event) { if (dc && dc.readyState === 'open') { dc.send(JSON.stringify(event)); log(`→ ${event.type}`); } }

  function addDelta(speaker, delta) {
    if (!delta) return;
    let last = turns[turns.length - 1];
    if (!last || last.speaker !== speaker) { last = { speaker, text: '' }; turns.push(last); }
    last.text += delta;
    render();
  }
  function render() {
    transcriptEl.innerHTML = '';
    for (const t of turns) {
      const div = document.createElement('div');
      div.className = 'turn ' + t.speaker;
      const b = document.createElement('b'); b.textContent = t.speaker === 'user' ? 'You' : 'Interviewer';
      div.append(b, document.createTextNode(t.text));
      transcriptEl.append(div);
    }
    transcriptEl.scrollTop = transcriptEl.scrollHeight;
    downloadBtn.disabled = turns.length === 0;
  }

  function onEvent(msg) {
    let e;
    try { e = JSON.parse(msg.data); } catch { return; }
    if (!['session.input_transcript.delta', 'session.output_transcript.delta', 'session.usage.updated'].includes(e.type)) log(`← ${e.type}`);
    switch (e.type) {
      case 'session.started':
        setStatus('Live', 'live');
        startedAt = performance.now();
        tick = setInterval(updateTimer, 500);
        // Silent context asking the interviewer to open. If it does not speak, the user can say hello.
        send({ type: 'session.thinking.append', delegation_id: null, content: 'The candidate has joined and is listening. Begin the interview now with your greeting and the disclosure.' });
        kickoffTimer = setTimeout(() => { if (!turns.length) hintEl.textContent = "If the interviewer hasn't spoken, say hello to begin."; }, 6000);
        break;
      case 'session.input_transcript.delta': addDelta('user', e.delta); break;
      case 'session.output_transcript.delta': hintEl.textContent = ''; addDelta('interviewer', e.delta); break;
      case 'session.usage.updated': if (e.usage && typeof e.usage.seconds === 'number') usageSeconds = e.usage.seconds; break;
      case 'session.delegation.created':
        // The spike has no backend. Give silent context so the interviewer carries on.
        send({ type: 'session.thinking.append', delegation_id: e.delegation && e.delegation.id, content: 'No further information is available. Continue the interview.' });
        break;
      case 'session.paused': setStatus('Paused (standby)'); break;
      case 'session.resumed': setStatus('Live', 'live'); break;
      case 'session.closed':
        if (e.usage && typeof e.usage.seconds === 'number') usageSeconds = e.usage.seconds;
        log(`  reason: ${e.reason || 'unknown'}`);
        teardown(e.reason === 'expired' ? 'Time limit reached' : 'Finished');
        break;
      case 'error':
        log(`  ${e.error && e.error.code ? e.error.code : ''} ${e.error && e.error.message ? e.error.message : ''}`);
        break;
    }
  }

  function updateTimer() {
    const elapsed = (performance.now() - startedAt) / 1000;
    const billed = Math.max(usageSeconds, elapsed);
    timerEl.textContent = `${fmt(elapsed)} of ${fmt(MAX_SECONDS)} · $${(billed / 60 * PRICE_PER_MINUTE).toFixed(2)}`;
    if (elapsed >= MAX_SECONDS && !hardStop) {
      hintEl.textContent = 'Time is up. Ending the session.';
      send({ type: 'session.close' });
      // If the provider doesn't confirm, cut the connection anyway so billing can't run on.
      hardStop = setTimeout(() => teardown('Time limit reached'), 10000);
    }
  }

  function waitForIce(peer) {
    if (peer.iceGatheringState === 'complete') return Promise.resolve();
    return new Promise((resolve) => {
      const done = () => { if (peer.iceGatheringState === 'complete') { peer.removeEventListener('icegatheringstatechange', done); resolve(); } };
      peer.addEventListener('icegatheringstatechange', done);
      setTimeout(resolve, 3000);
    });
  }

  async function start() {
    startBtn.disabled = true;
    hintEl.textContent = '';
    setStatus('Asking for your microphone…');
    try {
      mic = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } });
      pc = new RTCPeerConnection();
      pc.ontrack = (ev) => { $('remote').srcObject = ev.streams[0]; };
      pc.onconnectionstatechange = () => { log(`connection: ${pc.connectionState}`); if (pc.connectionState === 'failed') teardown('Connection lost', true); };
      mic.getTracks().forEach((t) => pc.addTrack(t, mic));
      dc = pc.createDataChannel('oai-events');
      dc.addEventListener('message', onEvent);

      setStatus('Connecting…');
      await pc.setLocalDescription(await pc.createOffer());
      await waitForIce(pc);

      const res = await fetch(SESSION_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ sdp: pc.localDescription.sdp }),
        credentials: 'same-origin',
      });
      const body = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(body.message || (res.status === 429 ? 'Too many sessions started this hour.' : `The server returned ${res.status}.`));
      log(`session ${body.session_id} (${body.provider})`);
      await pc.setRemoteDescription({ type: 'answer', sdp: body.sdp });
      endBtn.disabled = false;
    } catch (err) {
      teardown(err && err.message ? err.message : 'Could not start', true);
    }
  }

  function teardown(reason, isError) {
    clearInterval(tick); clearTimeout(hardStop); clearTimeout(kickoffTimer);
    tick = hardStop = kickoffTimer = null;
    if (dc) { try { dc.close(); } catch {} }
    if (pc) { try { pc.close(); } catch {} }
    if (mic) mic.getTracks().forEach((t) => t.stop());
    dc = pc = mic = null;
    setStatus(reason, isError ? 'error' : '');
    startBtn.disabled = false; startBtn.textContent = 'Start again';
    endBtn.disabled = true;
  }

  startBtn.addEventListener('click', start);
  endBtn.addEventListener('click', () => { endBtn.disabled = true; send({ type: 'session.close' }); hardStop = setTimeout(() => teardown('Finished'), 10000); });
  downloadBtn.addEventListener('click', () => {
    const text = turns.map((t) => `${t.speaker === 'user' ? 'You' : 'Interviewer'}: ${t.text.trim()}`).join('\n\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([text], { type: 'text/plain' }));
    a.download = `practice-interview-${new Date().toISOString().slice(0, 16).replace(/[:T]/g, '-')}.txt`;
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
  });
  window.addEventListener('pagehide', () => { if (dc) send({ type: 'session.close' }); });
})();
</script>
</body>
</html>
