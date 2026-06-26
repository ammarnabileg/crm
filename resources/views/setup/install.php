<?php $this->extends('layouts.guest'); ?>
<?php $this->section('content'); ?>
<div class="mx-auto max-w-5xl px-4 py-10">
    <header class="mb-8 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-600 text-2xl font-bold text-white shadow-lg">H</div>
        <h1 class="text-3xl font-bold text-slate-900"><?= e(config('app.name')) ?> — Setup</h1>
        <p class="mt-2 text-slate-600">No terminal required. Configure everything from this page and we'll do the rest.</p>
    </header>

    <div class="grid gap-6 lg:grid-cols-5">
        <!-- Steps -->
        <aside class="lg:col-span-2">
            <div class="card">
                <div class="card-body">
                    <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Installation steps</h2>
                    <ol class="space-y-2">
                        <?php
                        $labels = [
                            'requirements' => 'System requirements',
                            'database'     => 'Database connection',
                            'migrate'      => 'Create database tables',
                            'seed'         => 'Seed roles, permissions & plan',
                            'admin'        => 'Administrator account',
                            'finalize'     => 'Finalize & secure',
                        ];
                        foreach ($steps as $step):
                            $done = in_array($step, $state['completed'] ?? [], true);
                        ?>
                            <li id="step-<?= e($step) ?>" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm"
                                data-step="<?= e($step) ?>">
                                <span class="step-icon flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold <?= $done ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-400' ?>">
                                    <?= $done ? '&#10003;' : (string) (array_search($step, $steps, true) + 1) ?>
                                </span>
                                <span class="font-medium text-slate-700"><?= e($labels[$step] ?? $step) ?></span>
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
                        <h3 class="mb-4 font-semibold text-slate-900">1. Database connection</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-1">
                                <label class="label" for="db_host">Host</label>
                                <input class="input" id="db_host" name="db_host" value="127.0.0.1" required>
                            </div>
                            <div class="col-span-1">
                                <label class="label" for="db_port">Port</label>
                                <input class="input" id="db_port" name="db_port" value="3306" required>
                            </div>
                            <div class="col-span-2">
                                <label class="label" for="db_database">Database name</label>
                                <input class="input" id="db_database" name="db_database" placeholder="halaops" required>
                            </div>
                            <div class="col-span-1">
                                <label class="label" for="db_username">Username</label>
                                <input class="input" id="db_username" name="db_username" placeholder="root" required>
                            </div>
                            <div class="col-span-1">
                                <label class="label" for="db_password">Password</label>
                                <input class="input" id="db_password" name="db_password" type="password" autocomplete="off">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-4 font-semibold text-slate-900">2. Administrator account</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <label class="label" for="admin_name">Full name</label>
                                <input class="input" id="admin_name" name="admin_name" placeholder="Jane Doe" required>
                            </div>
                            <div class="col-span-2">
                                <label class="label" for="admin_email">Email</label>
                                <input class="input" id="admin_email" name="admin_email" type="email" placeholder="admin@company.com" required>
                            </div>
                            <div class="col-span-2">
                                <label class="label" for="admin_password">Password <span class="text-slate-400">(min 8 characters)</span></label>
                                <input class="input" id="admin_password" name="admin_password" type="password" minlength="8" autocomplete="new-password" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <h3 class="mb-4 font-semibold text-slate-900">3. Application</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-1">
                                <label class="label" for="app_name">App name</label>
                                <input class="input" id="app_name" name="app_name" value="<?= e(config('app.name')) ?>" required>
                            </div>
                            <div class="col-span-1">
                                <label class="label" for="app_url">App URL</label>
                                <input class="input" id="app_url" name="app_url" value="<?= e($defaultUrl) ?>" required>
                            </div>
                        </div>
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
                <pre id="console" class="m-0 max-h-80 overflow-auto bg-slate-900 p-4 text-xs leading-relaxed text-slate-200 font-mono"><span class="text-slate-500"># Ready. Fill in the form and click "Run installation".</span>
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
    const form = document.getElementById('install-form');

    function log(message, type) {
        const colors = { ok: 'text-green-400', err: 'text-red-400', info: 'text-brand-300', muted: 'text-slate-500' };
        const line = document.createElement('span');
        line.className = colors[type] || 'text-slate-200';
        const time = new Date().toLocaleTimeString();
        line.textContent = `[${time}] ${message}\n`;
        consoleEl.appendChild(line);
        consoleEl.scrollTop = consoleEl.scrollHeight;
    }

    function markStep(step, ok) {
        const el = document.getElementById('step-' + step);
        if (!el) return;
        const icon = el.querySelector('.step-icon');
        icon.innerHTML = ok ? '&#10003;' : '!';
        icon.className = 'step-icon flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold '
            + (ok ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700');
    }

    async function call(endpoint, body) {
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: body || new FormData(form),
        });
        let data;
        try { data = await res.json(); } catch (e) { throw new Error('Unexpected server response (HTTP ' + res.status + ').'); }
        if (!data.ok && data.ok !== undefined) throw new Error(data.message || ('HTTP ' + res.status));
        return data;
    }

    async function checkRequirements() {
        log('Checking system requirements...', 'info');
        const res = await fetch('<?= e(url('install/requirements')) ?>', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        });
        const data = await res.json();
        data.checks.forEach(c => log(`  ${c.ok ? '✓' : (c.required ? '✗' : '•')} ${c.name}: ${c.value}`, c.ok ? 'ok' : (c.required ? 'err' : 'muted')));
        markStep('requirements', data.passed);
        if (!data.passed) { log('Some required checks failed. Please fix them and re-check.', 'err'); }
        else { log('All required checks passed.', 'ok'); }
        return data.passed;
    }

    checkBtn.addEventListener('click', checkRequirements);

    const STEPS = [
        ['database', '<?= e(url('install/database')) ?>', 'Configuring database'],
        ['migrate',  '<?= e(url('install/migrate')) ?>',  'Creating tables'],
        ['seed',     '<?= e(url('install/seed')) ?>',     'Seeding data'],
        ['admin',    '<?= e(url('install/admin')) ?>',    'Creating administrator'],
        ['finalize', '<?= e(url('install/finalize')) ?>', 'Finalizing'],
    ];

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!form.reportValidity()) return;
        installBtn.disabled = true;
        checkBtn.disabled = true;
        installBtn.textContent = 'Installing...';

        try {
            const ok = await checkRequirements();
            if (!ok) throw new Error('Requirements not met.');

            for (const [step, endpoint, label] of STEPS) {
                log(label + '...', 'info');
                const data = await call(endpoint);
                if (data.log) {
                    data.log.forEach(m => log('   ' + (m.ok ? '✓' : '✗') + ' ' + m.name + (m.error ? ' — ' + m.error : ''), m.ok ? 'ok' : 'err'));
                }
                log('   ' + (data.message || 'done'), 'ok');
                markStep(step, true);
                if (data.redirect) {
                    log('Redirecting to login...', 'info');
                    setTimeout(() => window.location.href = data.redirect, 1200);
                }
            }
        } catch (err) {
            log('ERROR: ' + err.message, 'err');
            log('You can fix the issue and click "Run installation" again — completed steps are skipped.', 'muted');
            installBtn.disabled = false;
            checkBtn.disabled = false;
            installBtn.textContent = 'Retry installation →';
        }
    });

    // Auto-run the requirements check on load.
    checkRequirements();
})();
</script>
<?php $this->endSection(); ?>
