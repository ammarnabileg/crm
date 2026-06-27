<?php $this->extends('layouts.guest'); ?>
<?php $this->section('content'); ?>
<div class="mx-auto max-w-5xl px-4 py-10">
    <header class="mb-6 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-600 text-2xl font-bold text-white shadow-lg">H</div>
        <h1 class="text-3xl font-bold text-slate-900"><?= e(config('app.name')) ?> — Setup</h1>
        <p class="mt-2 text-slate-600">No terminal required. Configure everything from this page and we'll do the rest.</p>
    </header>

    <!-- Real progress bar -->
    <div class="mb-6">
        <div class="mb-1 flex items-center justify-between text-xs font-medium text-slate-500">
            <span id="progress-label">Getting ready…</span>
            <span id="progress-pct"><?= (int) $progress ?>%</span>
        </div>
        <div class="h-2 w-full overflow-hidden rounded-full bg-slate-200">
            <div id="progress-bar" class="h-2 rounded-full bg-brand-600 transition-all duration-500" style="width: <?= (int) $progress ?>%"></div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        <!-- 12 wizard steps (Bible) -->
        <aside class="lg:col-span-2">
            <div class="card">
                <div class="card-body">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Installation steps</h2>
                    <ol class="space-y-1.5">
                        <?php
                        $wizard = [
                            ['welcome', 'Welcome'],
                            ['systemcheck', 'System check'],
                            ['servercheck', 'Server check'],
                            ['extensions', 'PHP extensions'],
                            ['database', 'Database'],
                            ['environment', 'Environment keys'],
                            ['storage', 'Storage folders'],
                            ['permissions', 'Permissions'],
                            ['mail', 'Mail'],
                            ['ai', 'AI providers'],
                            ['admin', 'Super admin'],
                            ['health', 'Final health check'],
                        ];
                        foreach ($wizard as $i => [$key, $label]):
                        ?>
                            <li id="ui-<?= e($key) ?>" class="flex items-center gap-3 rounded-lg px-3 py-1.5 text-sm">
                                <span class="step-icon flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-400"><?= $i + 1 ?></span>
                                <span class="font-medium text-slate-700"><?= e($label) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
        </aside>

        <!-- Forms + console -->
        <main class="lg:col-span-3 space-y-6">
            <form id="install-form" class="space-y-6">
                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-4 font-semibold text-slate-900">Database connection</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="label" for="db_host">Host</label><input class="input" id="db_host" name="db_host" value="127.0.0.1" required></div>
                            <div><label class="label" for="db_port">Port</label><input class="input" id="db_port" name="db_port" value="3306" required></div>
                            <div class="col-span-2"><label class="label" for="db_database">Database name</label><input class="input" id="db_database" name="db_database" placeholder="halaops" required></div>
                            <div><label class="label" for="db_username">Username</label><input class="input" id="db_username" name="db_username" placeholder="root" required></div>
                            <div><label class="label" for="db_password">Password</label><input class="input" id="db_password" name="db_password" type="password" autocomplete="off"></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-1 font-semibold text-slate-900">Mail <span class="text-xs font-normal text-slate-400">(optional)</span></h3>
                        <p class="mb-4 text-xs text-slate-500">Used for invites and password resets. You can change this later from the dashboard. When disabled, messages are written to <code>storage/logs</code>.</p>
                        <label class="mb-3 flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" id="mail_enabled" name="mail_enabled" value="1" class="rounded border-slate-300"> Enable email delivery (server <code>mail()</code>)
                        </label>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="label" for="mail_from_address">From address</label><input class="input" id="mail_from_address" name="mail_from_address" value="no-reply@halaops.local"></div>
                            <div><label class="label" for="mail_from_name">From name</label><input class="input" id="mail_from_name" name="mail_from_name" value="<?= e(config('app.name')) ?>"></div>
                            <div class="col-span-2 flex items-end gap-2">
                                <div class="flex-1"><label class="label" for="test_email">Send a test to</label><input class="input" id="test_email" name="test_email" type="email" placeholder="you@example.com"></div>
                                <button type="button" id="test-mail-btn" class="btn-secondary whitespace-nowrap">Send test email</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-4 font-semibold text-slate-900">Administrator account</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2"><label class="label" for="admin_name">Full name</label><input class="input" id="admin_name" name="admin_name" placeholder="Jane Doe" required></div>
                            <div class="col-span-2"><label class="label" for="admin_email">Email</label><input class="input" id="admin_email" name="admin_email" type="email" placeholder="admin@workspace.com" required></div>
                            <div class="col-span-2"><label class="label" for="admin_password">Password <span class="text-slate-400">(min 8 characters)</span></label><input class="input" id="admin_password" name="admin_password" type="password" minlength="8" autocomplete="new-password" required></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-4 font-semibold text-slate-900">Application</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="label" for="app_name">App name</label><input class="input" id="app_name" name="app_name" value="<?= e(config('app.name')) ?>" required></div>
                            <div><label class="label" for="app_url">App URL</label><input class="input" id="app_url" name="app_url" value="<?= e($defaultUrl) ?>" required></div>
                        </div>
                    </div>
                </div>

                <div class="card border-l-4 border-brand-400">
                    <div class="card-body">
                        <h3 class="mb-1 font-semibold text-slate-900">AI providers</h3>
                        <p class="text-sm text-slate-600">No AI keys are needed here. Each workspace adds its own provider keys (OpenAI, Anthropic, Gemini, DeepSeek, Azure, HeyGen…) after signing in — keys are stored encrypted and never shared across workspaces.</p>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-4">
                    <button type="button" id="check-btn" class="btn-secondary">Re-check requirements</button>
                    <button type="submit" id="install-btn" class="btn-primary">Run installation &rarr;</button>
                </div>
            </form>

            <!-- Live console -->
            <div class="card overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-2">
                    <h3 class="text-sm font-semibold text-slate-700">Installation console</h3>
                    <span class="badge-slate">live</span>
                </div>
                <pre id="console" class="m-0 max-h-96 overflow-auto bg-slate-900 p-4 text-xs leading-relaxed text-slate-200 font-mono"><span class="text-slate-500"># Ready. Fill in the form and click "Run installation".</span>
</pre>
            </div>
        </main>
    </div>
</div>
<?php $this->endSection(); ?>

<?php $this->section('scripts'); ?>
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const consoleEl = document.getElementById('console');
    const installBtn = document.getElementById('install-btn');
    const checkBtn = document.getElementById('check-btn');
    const testMailBtn = document.getElementById('test-mail-btn');
    const form = document.getElementById('install-form');
    const bar = document.getElementById('progress-bar');
    const pct = document.getElementById('progress-pct');
    const progressLabel = document.getElementById('progress-label');

    const U = {
        requirements: '<?= e(url('setup/requirements')) ?>',
        database: '<?= e(url('setup/database')) ?>',
        environment: '<?= e(url('setup/environment')) ?>',
        storage: '<?= e(url('setup/storage')) ?>',
        permissions: '<?= e(url('setup/permissions')) ?>',
        fix: '<?= e(url('setup/permissions/fix')) ?>',
        migrate: '<?= e(url('setup/migrate')) ?>',
        seed: '<?= e(url('setup/seed')) ?>',
        mail: '<?= e(url('setup/mail')) ?>',
        testMail: '<?= e(url('setup/mail/test')) ?>',
        admin: '<?= e(url('setup/admin')) ?>',
        health: '<?= e(url('setup/health')) ?>',
        finalize: '<?= e(url('setup/finalize')) ?>',
    };

    function log(message, type) {
        const colors = { ok: 'text-green-400', err: 'text-red-400', info: 'text-brand-300', warn: 'text-amber-300', muted: 'text-slate-500' };
        const line = document.createElement('span');
        line.className = colors[type] || 'text-slate-200';
        line.textContent = `[${new Date().toLocaleTimeString()}] ${message}\n`;
        consoleEl.appendChild(line);
        consoleEl.scrollTop = consoleEl.scrollHeight;
    }

    function setProgress(p, label) {
        p = Math.max(0, Math.min(100, Math.round(p)));
        bar.style.width = p + '%';
        pct.textContent = p + '%';
        if (label) progressLabel.textContent = label;
    }

    function tick(uiKey, ok) {
        const el = document.getElementById('ui-' + uiKey);
        if (!el) return;
        const icon = el.querySelector('.step-icon');
        icon.innerHTML = ok ? '&#10003;' : '!';
        icon.className = 'step-icon flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold '
            + (ok ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700');
    }

    async function post(endpoint) {
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: new FormData(form),
        });
        let data;
        try { data = await res.json(); } catch (e) { throw new Error('Unexpected server response (HTTP ' + res.status + ').'); }
        if (data.ok === false) throw new Error(data.message || ('HTTP ' + res.status));
        return data;
    }

    async function get(endpoint) {
        const res = await fetch(endpoint, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
        return res.json();
    }

    async function checkRequirements() {
        log('Checking system requirements…', 'info');
        const data = await get(U.requirements);
        for (const [group, checks] of Object.entries(data.groups || {})) {
            log('• ' + group, 'info');
            checks.forEach(c => {
                log(`   ${c.ok ? '✓' : (c.required ? '✗' : '•')} ${c.name}: ${c.value}`, c.ok ? 'ok' : (c.required ? 'err' : 'muted'));
                if (!c.ok && c.solution) log('       → ' + c.solution, 'warn');
            });
        }
        const ok = data.passed;
        tick('systemcheck', ok); tick('servercheck', ok); tick('extensions', ok); tick('welcome', true);
        log(ok ? 'All required checks passed.' : 'Some required checks failed — see the suggested fixes above.', ok ? 'ok' : 'err');
        return ok;
    }

    checkBtn.addEventListener('click', checkRequirements);

    testMailBtn.addEventListener('click', async function () {
        try {
            log('Sending test email…', 'info');
            const data = await post(U.testMail);
            log('   ' + data.message, data.ok ? 'ok' : 'warn');
        } catch (e) { log('   ' + e.message, 'err'); }
    });

    // Operation sequence → [uiKey to tick, endpoint, label, isMail?]
    const SEQ = [
        ['database',     U.database,     'Configuring database'],
        ['environment',  U.environment,  'Generating environment keys'],
        ['storage',      U.storage,      'Creating storage folders'],
        ['permissions',  U.permissions,  'Verifying permissions'],
        [null,           U.migrate,      'Creating database tables'],
        [null,           U.seed,         'Seeding reference data, roles & permissions'],
        ['mail',         U.mail,         'Saving mail settings'],
        ['admin',        U.admin,        'Creating administrator'],
    ];

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!form.reportValidity()) return;
        installBtn.disabled = true; checkBtn.disabled = true;
        installBtn.textContent = 'Installing…';

        const total = SEQ.length + 3; // requirements + health + finalize
        let done = 0;
        const step = () => setProgress((++done / total) * 100);

        try {
            setProgress(2, 'Checking requirements…');
            if (!await checkRequirements()) throw new Error('Requirements not met — apply the suggested fixes and retry.');
            step();

            for (const [uiKey, endpoint, label] of SEQ) {
                setProgress((done / total) * 100, label + '…');
                log(label + '…', 'info');
                let data;
                try {
                    data = await post(endpoint);
                } catch (err) {
                    // Permissions can be auto-repaired without a terminal.
                    if (endpoint === U.permissions) {
                        log('   Some folders are not writable — attempting Auto-Fix…', 'warn');
                        const fix = await post(U.fix);
                        (fix.log || []).forEach(m => log('      ' + (m.ok ? '✓' : '✗') + ' ' + m.name, m.ok ? 'ok' : 'err'));
                        if (!fix.ok_fixed) throw new Error(fix.message);
                        log('   ' + fix.message, 'ok');
                        if (uiKey) tick(uiKey, true);
                        step();
                        continue;
                    }
                    throw err;
                }
                (data.log || []).forEach(m => log('   ' + (m.ok ? '✓' : '✗') + ' ' + m.name + (m.error ? ' — ' + m.error : ''), m.ok ? 'ok' : 'err'));
                log('   ' + (data.message || 'done'), 'ok');
                if (uiKey) tick(uiKey, true);
                step();
            }

            // Final health check.
            setProgress((done / total) * 100, 'Running final health check…');
            log('Running final health check…', 'info');
            const health = await get(U.health);
            (health.checks || []).forEach(c => log('   ' + (c.ok ? '✓' : '✗') + ' ' + c.name + ': ' + c.value, c.ok ? 'ok' : 'err'));
            tick('health', health.passed);
            log(health.passed ? 'Health check passed.' : 'Health check reported issues (continuing — finalize will validate).', health.passed ? 'ok' : 'warn');
            step();

            // Finalize (runs the real final validation server-side).
            setProgress((done / total) * 100, 'Finalizing & validating…');
            log('Finalizing & running final validation…', 'info');
            const fin = await post(U.finalize);
            log('   ' + fin.message, 'ok');
            setProgress(100, 'Installation complete');
            if (fin.redirect) { log('Redirecting to sign in…', 'info'); setTimeout(() => window.location.href = fin.redirect, 1400); }
        } catch (err) {
            log('ERROR: ' + err.message, 'err');
            log('Fix the issue and click "Retry installation" — completed steps are skipped automatically.', 'muted');
            installBtn.disabled = false; checkBtn.disabled = false;
            installBtn.textContent = 'Retry installation →';
        }
    });

    // Auto-run requirements on load + mark AI step as informational.
    tick('ai', true);
    checkRequirements();
})();
</script>
<?php $this->endSection(); ?>
