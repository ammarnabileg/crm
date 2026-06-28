<?php
/** @var array<string,mixed> $interview */
/** @var array<string,mixed> $state */
/** @var string $applicationId */
/** @var string $workspaceName */

$done = (bool) $state['done'];
$asked = (int) $state['asked'];
$max = (int) $state['max_questions'];
$remaining = (int) $state['seconds_remaining'];
?>
<div class="mx-auto max-w-3xl">
    <?php if ($done): ?>
        <!-- Completion screen -->
        <div class="rounded-2xl border border-emerald-200 bg-white p-10 text-center shadow-sm">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-2xl text-emerald-600">✓</div>
            <h1 class="text-2xl font-semibold text-slate-900">Interview completed successfully</h1>
            <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">Thank you for your time and thoughtful answers. The hiring team at <?= e($workspaceName) ?> will review your interview and follow up on your application.</p>
            <div class="mt-6 flex justify-center gap-3">
                <a href="/portal/applications/<?= e($applicationId) ?>" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">View my application</a>
                <a href="/portal" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to portal</a>
            </div>
        </div>
    <?php else: ?>
        <!-- Header: progress + timer -->
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold text-slate-900">AI Interview · <?= e($interview['job_title'] ?? '') ?></h1>
                <p class="text-xs text-slate-400">Answer in your own words. You can pause and resume within the time window.</p>
            </div>
            <div class="text-right">
                <div class="text-xs text-slate-400">Question <span class="font-semibold text-slate-700"><?= e(min($asked, $max)) ?></span> / <?= e($max) ?></div>
                <div class="text-sm font-semibold text-slate-700" data-timer data-remaining="<?= e($remaining) ?>">--:--</div>
            </div>
        </div>

        <!-- Conversation -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="max-h-[55vh] space-y-3 overflow-y-auto pr-1" data-log>
                <?php foreach ($state['messages'] as $m): ?>
                    <?php $isCand = (string) $m['role'] === 'candidate'; ?>
                    <div class="flex <?= $isCand ? 'justify-end' : 'justify-start' ?>">
                        <div class="max-w-[80%] rounded-2xl px-4 py-2 text-sm <?= $isCand ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-800' ?>">
                            <?= nl2br(e((string) $m['content'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="post" action="/portal/interview/<?= e($interview['id']) ?>/answer" class="mt-4 border-t border-slate-100 pt-4" data-answer-form data-transcribe-url="/portal/interview/<?= e($interview['id']) ?>/transcribe">
                <?= csrf_field() ?>
                <textarea name="answer" rows="3" required autofocus placeholder="Type your answer…" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none" data-answer></textarea>
                <div class="mt-2 flex items-center justify-between gap-2">
                    <div class="flex gap-2">
                        <button type="button" data-mic class="hidden items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">
                            🎙 <span data-mic-label>Speak</span>
                        </button>
                        <button type="button" data-rec class="hidden items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">
                            🎤 <span data-rec-label>Record (AI)</span>
                        </button>
                    </div>
                    <div class="ml-auto flex gap-2">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Send answer</button>
                    </div>
                </div>
                <p class="mt-2 text-xs text-slate-400">This AI interview is advisory — a human always makes the final decision.</p>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php if (! $done): ?>
<script>
(function () {
    // Auto-scroll the conversation to the latest message.
    var log = document.querySelector('[data-log]');
    if (log) log.scrollTop = log.scrollHeight;

    // Countdown timer. When it reaches zero, submit to let the server finalize.
    var el = document.querySelector('[data-timer]');
    if (el) {
        var remaining = parseInt(el.getAttribute('data-remaining'), 10) || 0;
        var form = document.querySelector('[data-answer-form]');
        var tick = function () {
            var m = Math.floor(remaining / 60), s = remaining % 60;
            el.textContent = m + ':' + (s < 10 ? '0' : '') + s;
            if (remaining <= 0) {
                if (form) { var t = form.querySelector('[data-answer]'); if (t) t.required = false; form.submit(); }
                return;
            }
            remaining--; setTimeout(tick, 1000);
        };
        tick();
    }

    // Voice (mode B): browser speech-to-text writes into the answer box.
    var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    var mic = document.querySelector('[data-mic]');
    var answer = document.querySelector('[data-answer]');
    if (SR && mic && answer) {
        mic.classList.remove('hidden'); mic.classList.add('inline-flex');
        var rec = new SR(); rec.continuous = true; rec.interimResults = false; rec.lang = 'en-US';
        var on = false;
        var label = mic.querySelector('[data-mic-label]');
        mic.addEventListener('click', function () {
            on ? rec.stop() : rec.start();
        });
        rec.onstart = function () { on = true; if (label) label.textContent = 'Listening… (tap to stop)'; };
        rec.onend = function () { on = false; if (label) label.textContent = 'Speak'; };
        rec.onresult = function (e) {
            for (var i = e.resultIndex; i < e.results.length; i++) {
                if (e.results[i].isFinal) answer.value += (answer.value ? ' ' : '') + e.results[i][0].transcript.trim();
            }
        };
    }

    // Voice (mode B) — server-side: record audio and transcribe via the
    // workspace's OpenAI (Whisper) key. Falls back to a hint if unavailable.
    var form2 = document.querySelector('[data-answer-form]');
    var recBtn = document.querySelector('[data-rec]');
    var recLabel = recBtn ? recBtn.querySelector('[data-rec-label]') : null;
    var csrf = form2 ? (form2.querySelector('input[name="_csrf"]') || {}).value : '';
    if (form2 && recBtn && answer && navigator.mediaDevices && window.MediaRecorder) {
        recBtn.classList.remove('hidden'); recBtn.classList.add('inline-flex');
        var mediaRec = null, chunks = [], recording = false;
        var setLabel = function (t) { if (recLabel) recLabel.textContent = t; };
        recBtn.addEventListener('click', function () {
            if (recording && mediaRec) { mediaRec.stop(); return; }
            navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
                chunks = []; mediaRec = new MediaRecorder(stream);
                mediaRec.ondataavailable = function (e) { if (e.data && e.data.size) chunks.push(e.data); };
                mediaRec.onstop = function () {
                    stream.getTracks().forEach(function (t) { t.stop(); });
                    recording = false; setLabel('Transcribing…'); recBtn.disabled = true;
                    var blob = new Blob(chunks, { type: 'audio/webm' });
                    var fd = new FormData(); fd.append('_csrf', csrf); fd.append('audio', blob, 'answer.webm');
                    fetch(form2.getAttribute('data-transcribe-url'), { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } })
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (j && j.text) { answer.value += (answer.value ? ' ' : '') + j.text; }
                            else { alert('Voice transcription is not configured for this workspace. Please type your answer or use “Speak”.'); }
                        })
                        .catch(function () { alert('Could not transcribe the recording. Please type your answer.'); })
                        .finally(function () { setLabel('Record (AI)'); recBtn.disabled = false; });
                };
                mediaRec.start(); recording = true; setLabel('Recording… (tap to stop)');
            }).catch(function () { alert('Microphone permission is required to record.'); });
        });
    }
})();
</script>
<?php endif; ?>
