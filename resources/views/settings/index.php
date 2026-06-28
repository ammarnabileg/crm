<?php
/** @var array<string,mixed> $workspace */
/** @var array<string,string> $prefs */
/** @var bool $canUpdate */
/** @var bool $hasLogo */
/** @var string $logoVersion */
/** @var bool $mailPasswordSet */
/** @var string|null $status */
/** @var string|null $error */
$p = static fn (string $k): string => (string) ($prefs[$k] ?? '');
$on = static fn (string $k): bool => ($prefs[$k] ?? '') === '1';
$dis = $canUpdate ? '' : 'disabled';
$inp = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none disabled:bg-slate-50';
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-slate-900">Workspace settings</h1>
    <p class="mt-1 text-sm text-slate-500">Each workspace has its own independent settings.</p>
</div>

<?php if ($status): ?><div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><?= e($status) ?></div><?php endif; ?>
<?php if (! empty($error)): ?><div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700"><?= e($error) ?></div><?php endif; ?>

<form method="post" action="/settings" enctype="multipart/form-data" class="max-w-2xl space-y-6">
    <?= csrf_field() ?>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-slate-900">General</h2>
        <div class="space-y-4">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Workspace name</label>
                <input name="name" value="<?= e($workspace['name']) ?>" <?= $dis ?> class="<?= $inp ?>">
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div><label class="mb-1 block text-sm font-medium text-slate-700">Timezone</label><input name="timezone" value="<?= e($workspace['timezone']) ?>" <?= $dis ?> class="<?= $inp ?>"></div>
                <div><label class="mb-1 block text-sm font-medium text-slate-700">Date format</label>
                    <?php $df = $p('general.date_format') ?: 'Y-m-d'; ?>
                    <select name="general_date_format" <?= $dis ?> class="<?= $inp ?>">
                        <?php foreach (['Y-m-d' => '2026-06-28', 'd/m/Y' => '28/06/2026', 'm/d/Y' => '06/28/2026', 'd M Y' => '28 Jun 2026'] as $v => $eg): ?>
                            <option value="<?= e($v) ?>" <?= $df === $v ? 'selected' : '' ?>><?= e($eg) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label class="mb-1 block text-sm font-medium text-slate-700">Locale</label><input name="locale" value="<?= e($workspace['locale']) ?>" <?= $dis ?> class="<?= $inp ?>"></div>
                <div><label class="mb-1 block text-sm font-medium text-slate-700">Currency</label><input name="currency" value="<?= e($workspace['currency']) ?>" <?= $dis ?> class="<?= $inp ?>"></div>
            </div>
            <div class="text-xs text-slate-400">Slug: <span class="font-mono"><?= e($workspace['slug']) ?></span></div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-slate-900">Company profile</h2>
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div><label class="mb-1 block text-sm font-medium text-slate-700">Industry</label><input name="company_industry" value="<?= e($p('company.industry')) ?>" <?= $dis ?> class="<?= $inp ?>" placeholder="e.g. Software"></div>
                <div><label class="mb-1 block text-sm font-medium text-slate-700">Website</label><input name="company_website" value="<?= e($p('company.website')) ?>" <?= $dis ?> class="<?= $inp ?>" placeholder="https://"></div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="mb-1 block text-sm font-medium text-slate-700">Contact email</label><input name="company_contact_email" type="email" value="<?= e($p('company.contact_email')) ?>" <?= $dis ?> class="<?= $inp ?>" placeholder="careers@acme.com"></div>
                <div><label class="mb-1 block text-sm font-medium text-slate-700">Contact phone</label><input name="company_contact_phone" value="<?= e($p('company.contact_phone')) ?>" <?= $dis ?> class="<?= $inp ?>" placeholder="+20 …"></div>
            </div>
            <div><label class="mb-1 block text-sm font-medium text-slate-700">About</label><textarea name="company_about" rows="3" <?= $dis ?> class="<?= $inp ?>" placeholder="What your company does…"><?= e($p('company.about')) ?></textarea></div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-slate-900">Branding</h2>
        <div class="mb-4 flex items-center gap-4">
            <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                <?php if ($hasLogo): ?>
                    <img src="/settings/logo?v=<?= e($logoVersion) ?>" alt="Workspace logo" class="h-full w-full object-contain">
                <?php else: ?>
                    <span class="text-xs text-slate-400">No logo</span>
                <?php endif; ?>
            </div>
            <div class="grow">
                <label class="mb-1 block text-sm font-medium text-slate-700">Logo</label>
                <input name="logo" type="file" accept="image/png,image/jpeg,image/webp,image/gif" <?= $dis ?> class="block w-full text-sm text-slate-500 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                <p class="mt-1 text-xs text-slate-400">PNG, JPG, WEBP or GIF · up to 10 MB.</p>
                <?php if ($hasLogo && $canUpdate): ?>
                    <label class="mt-2 flex items-center gap-2 text-xs text-slate-500"><input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300"> Remove current logo</label>
                <?php endif; ?>
            </div>
        </div>
        <div class="grid grid-cols-3 gap-3">
            <div><label class="mb-1 block text-sm font-medium text-slate-700">Brand colour</label><input name="brand_color" type="text" value="<?= e($p('brand.color')) ?>" <?= $dis ?> class="<?= $inp ?>" placeholder="#4f46e5"></div>
            <div><label class="mb-1 block text-sm font-medium text-slate-700">Logo text</label><input name="brand_logo_text" value="<?= e($p('brand.logo_text')) ?>" <?= $dis ?> class="<?= $inp ?>" placeholder="Shown if no logo image"></div>
            <div><label class="mb-1 block text-sm font-medium text-slate-700">Tagline</label><input name="brand_tagline" value="<?= e($p('brand.tagline')) ?>" <?= $dis ?> class="<?= $inp ?>" placeholder="Hiring, reimagined"></div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-1 text-sm font-semibold text-slate-900">Email (SMTP)</h2>
        <p class="mb-4 text-xs text-slate-400">Outbound email for invitations, offers and notifications is sent from this workspace's own mailbox.</p>
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div><label class="mb-1 block text-sm font-medium text-slate-700">From name</label><input name="mail_from_name" value="<?= e($p('mail.from_name')) ?>" <?= $dis ?> class="<?= $inp ?>" placeholder="Acme Talent"></div>
                <div><label class="mb-1 block text-sm font-medium text-slate-700">From email</label><input name="mail_from_email" type="email" value="<?= e($p('mail.from_email')) ?>" <?= $dis ?> class="<?= $inp ?>" placeholder="hiring@acme.com"></div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-2"><label class="mb-1 block text-sm font-medium text-slate-700">SMTP host</label><input name="mail_smtp_host" value="<?= e($p('mail.smtp_host')) ?>" <?= $dis ?> class="<?= $inp ?>" placeholder="smtp.mailgun.org"></div>
                <div><label class="mb-1 block text-sm font-medium text-slate-700">Port</label><input name="mail_smtp_port" type="number" value="<?= e($p('mail.smtp_port')) ?>" <?= $dis ?> class="<?= $inp ?>" placeholder="587"></div>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div><label class="mb-1 block text-sm font-medium text-slate-700">Username</label><input name="mail_smtp_username" value="<?= e($p('mail.smtp_username')) ?>" <?= $dis ?> class="<?= $inp ?>" autocomplete="off"></div>
                <div><label class="mb-1 block text-sm font-medium text-slate-700">Password</label><input name="mail_smtp_password" type="password" <?= $dis ?> class="<?= $inp ?>" autocomplete="new-password" placeholder="<?= $mailPasswordSet ? '•••••••• (set — leave blank to keep)' : 'not set' ?>"></div>
                <div><label class="mb-1 block text-sm font-medium text-slate-700">Encryption</label>
                    <select name="mail_encryption" <?= $dis ?> class="<?= $inp ?>">
                        <?php $enc = $p('mail.encryption'); foreach (['' => 'None', 'tls' => 'TLS', 'ssl' => 'SSL'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= $enc === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-1 text-sm font-semibold text-slate-900">Legal</h2>
        <p class="mb-4 text-xs text-slate-400">Shown to candidates on careers and application pages.</p>
        <div class="space-y-4">
            <div><label class="mb-1 block text-sm font-medium text-slate-700">Legal company name</label><input name="legal_company_legal_name" value="<?= e($p('legal.company_legal_name')) ?>" <?= $dis ?> class="<?= $inp ?>" placeholder="Acme Inc."></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="mb-1 block text-sm font-medium text-slate-700">Terms URL</label><input name="legal_terms_url" value="<?= e($p('legal.terms_url')) ?>" <?= $dis ?> class="<?= $inp ?>" placeholder="https://acme.com/terms"></div>
                <div><label class="mb-1 block text-sm font-medium text-slate-700">Privacy URL</label><input name="legal_privacy_url" value="<?= e($p('legal.privacy_url')) ?>" <?= $dis ?> class="<?= $inp ?>" placeholder="https://acme.com/privacy"></div>
            </div>
            <div><label class="mb-1 block text-sm font-medium text-slate-700">Registered address</label><textarea name="legal_address" rows="2" <?= $dis ?> class="<?= $inp ?>" placeholder="Street, City, Country"><?= e($p('legal.address')) ?></textarea></div>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-sm font-semibold text-slate-900">Security</h2>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="security_enforce_2fa" value="1" <?= $on('security.enforce_2fa') ? 'checked' : '' ?> <?= $dis ?> class="rounded border-slate-300"> Require two-factor authentication for members
        </label>
        <div class="mt-3"><label class="mb-1 block text-sm font-medium text-slate-700">Session timeout (minutes)</label><input name="security_session_timeout" type="number" min="5" value="<?= e($p('security.session_timeout')) ?>" <?= $dis ?> class="<?= $inp ?> max-w-[12rem]" placeholder="120"></div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="mb-1 text-sm font-semibold text-slate-900">Maintenance</h2>
        <p class="mb-3 text-xs text-slate-400">Pause the workspace for everyone except admins and allow-listed IPs.</p>
        <a href="/settings/maintenance" class="inline-block rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Open maintenance settings →</a>
    </div>

    <?php if ($canUpdate): ?>
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save settings</button>
    <?php endif; ?>
</form>

<?php if (! empty($isOwner)): ?>
    <div class="mt-6 max-w-2xl rounded-2xl border border-rose-200 bg-white p-6 shadow-sm">
        <h2 class="mb-1 text-sm font-semibold text-rose-700">Danger zone</h2>
        <p class="mb-4 text-xs text-slate-400">Owner-only actions for this workspace.</p>

        <form method="post" action="/workspaces/transfer-ownership" class="mb-5 flex flex-wrap items-end gap-2" onsubmit="return confirm('Transfer ownership to this user? You will keep your role but no longer be the owner.');">
            <?= csrf_field() ?>
            <label class="block text-xs font-medium text-slate-600">Transfer ownership to (email)
                <input name="email" type="email" required placeholder="new-owner@example.com" class="mt-1 w-72 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </label>
            <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Transfer</button>
        </form>

        <form method="post" action="/workspaces/archive" onsubmit="return confirm('Archive this workspace? It will be paused until restored.');">
            <?= csrf_field() ?>
            <button class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">Archive workspace</button>
        </form>
    </div>
<?php endif; ?>
