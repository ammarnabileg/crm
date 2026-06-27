<?php
/** Authenticated application shell: sidebar + topbar. */
$user = auth()->user();
$workspace = tenant()->workspace();
$current = request()->path();
// Navigation registry. Each entry: [path, label, permission, built?]. Only
// shipped features are rendered, so there are never dead links in the UI;
// upcoming sections are added here as their modules land.
$nav = [
    ['dashboard', 'Dashboard', 'dashboard.view', true],
    ['design',    'Design System',      'dashboard.view', true],
    // Recruitment / ATS (gated by recruitment.view).
    ['recruiter', 'Recruiter Workspace', 'recruitment.view', true],
    ['jobs',      'Jobs',                'recruitment.view', true],
    ['search',    'Search',              'recruitment.view', true],
    ['files',     'Files',               'recruitment.view', true],
    ['members',   'Members',            'members.view', true],
    ['roles',     'Roles & Permissions', 'roles.view',  false],
    ['ai',        'AI Settings',        'ai.view',      true],
    ['billing',   'Billing',            'billing.view', false],
    ['settings',  'Workspace Settings',   'settings.view', true],
    // System operations (super-admin only — gated by system.manage). No terminal.
    ['system/diagnostics', 'Diagnostics',      'system.manage', true],
    ['system/maintenance', 'Maintenance',      'system.manage', true],
    ['system/backups',     'Backup & Restore', 'system.manage', true],
    ['system/environment', 'Environment',      'system.manage', true],
    ['system/logs',        'Logs',             'system.manage', true],
];
$isActive = fn (string $path): bool => $current === '/' . trim($path, '/') || str_starts_with($current, '/' . trim($path, '/') . '/');
?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <script nonce="<?= e(csp_nonce()) ?>">
      // No-flash theme: apply the saved (or system) dark mode before first paint.
      (function () {
        try {
          var t = localStorage.getItem('halaops-theme');
          if (t === 'dark' || (!t && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
          }
        } catch (e) {}
      })();
    </script>
    <title><?= e(($title ?? 'Dashboard') . ' · ' . config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-200">
<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="hidden w-64 shrink-0 flex-col border-e border-slate-200 bg-white lg:flex dark:border-slate-800 dark:bg-slate-900">
        <div class="flex h-16 items-center gap-2 border-b border-slate-200 px-5 dark:border-slate-800">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 font-bold text-white">H</span>
            <span class="font-bold text-slate-900 dark:text-white"><?= e(config('app.name')) ?></span>
        </div>
        <nav class="flex-1 space-y-1 p-3">
            <?php foreach ($nav as [$path, $label, $perm, $built]): ?>
                <?php if ($built && can($perm)): ?>
                    <a href="<?= e(url($path)) ?>" class="nav-link <?= $isActive($path) ? 'nav-link-active' : '' ?>">
                        <span class="h-1.5 w-1.5 rounded-full bg-current opacity-60"></span>
                        <?= e($label) ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </aside>

    <!-- Main -->
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6 dark:border-slate-800 dark:bg-slate-900">
            <div class="flex min-w-0 items-center gap-2">
                <!-- Mobile navigation: the sidebar is hidden below lg, so expose the
                     same nav from a hamburger here (no page is unreachable on phones). -->
                <details class="relative lg:hidden">
                    <summary class="flex cursor-pointer list-none items-center rounded-lg p-2 hover:bg-slate-100" aria-label="Open navigation menu">
                        <svg class="h-6 w-6 text-slate-700" aria-hidden="true" focusable="false" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </summary>
                    <nav class="absolute start-0 z-30 mt-2 w-64 space-y-1 rounded-xl bg-white p-2 shadow-lg ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                        <?php foreach ($nav as [$path, $label, $perm, $built]): ?>
                            <?php if ($built && can($perm)): ?>
                                <a href="<?= e(url($path)) ?>" class="nav-link <?= $isActive($path) ? 'nav-link-active' : '' ?>">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-60"></span>
                                    <?= e($label) ?>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </nav>
                </details>
                <div class="min-w-0">
                <?php if ($workspace): ?>
                    <details class="relative">
                        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-100">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-100 text-sm font-semibold text-brand-700 dark:bg-brand-900/50 dark:text-brand-200"><?= e(mb_substr($workspace->name, 0, 1)) ?></span>
                            <span class="truncate font-semibold text-slate-800 dark:text-slate-100"><?= e($workspace->name) ?></span>
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </summary>
                        <div class="absolute z-20 mt-2 w-64 rounded-xl bg-white p-2 shadow-lg ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                            <?php foreach (($user?->workspaces() ?? []) as $c): ?>
                                <form method="POST" action="<?= e(url('workspaces/switch')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="workspace_id" value="<?= e($c['id']) ?>">
                                    <button class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-start text-sm hover:bg-slate-50 <?= (int) $c['id'] === tenant()->id() ? 'font-semibold text-brand-700' : 'text-slate-700' ?>">
                                        <span class="flex h-6 w-6 items-center justify-center rounded bg-slate-100 text-xs"><?= e(mb_substr($c['name'], 0, 1)) ?></span>
                                        <?= e($c['name']) ?>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                            <a href="<?= e(url('workspaces/create')) ?>" class="mt-1 flex items-center gap-2 rounded-lg border-t border-slate-100 px-2 py-2 text-sm text-brand-600 hover:bg-slate-50">+ New workspace</a>
                        </div>
                    </details>
                <?php else: ?>
                    <span class="font-semibold text-slate-800"><?= e(config('app.name')) ?></span>
                <?php endif; ?>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <?php if ($workspace && $user): // bell only with an active tenant + user ?>
                    <?php
                        $notifier = new \App\Services\Notifications\NotificationService();
                        $wsId = (int) tenant()->id();
                        $uid = (int) auth()->id();
                    ?>
                    <?= component('notification-center', [
                        'count'         => $notifier->unreadCount($wsId, $uid),
                        'viewAllHref'   => url('notifications'),
                        'markAllAction' => url('notifications/read-all'),
                        'items'         => array_map(static fn (array $n): array => [
                            'title' => (string) ($n['title'] ?? ''),
                            'time'  => (string) ($n['created_at'] ?? ''),
                            'read'  => ($n['read_at'] ?? null) !== null,
                            'href'  => url('notifications'),
                        ], $notifier->recent($wsId, $uid, 8)),
                    ]) ?>
                <?php endif; ?>
                <button type="button" data-theme-toggle class="btn-ghost px-2 py-1.5" aria-label="Toggle dark mode" aria-pressed="false" title="Toggle dark mode">
                    <svg class="h-5 w-5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    <svg class="hidden h-5 w-5 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                </button>
                <a href="<?= e(url(request()->path() === '/' ? '/' : ltrim($current, '/')) . '?lang=' . (is_rtl() ? 'en' : 'ar')) ?>"
                   class="btn-ghost px-2 py-1.5 text-sm" aria-label="<?= is_rtl() ? 'Switch language to English' : 'تغيير اللغة إلى العربية' ?>"><?= is_rtl() ? 'EN' : 'ع' ?></a>

                <details class="relative">
                    <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-100">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white"><?= e($user?->initials()) ?></span>
                        <span class="hidden text-sm font-medium text-slate-700 sm:block"><?= e($user?->name) ?></span>
                    </summary>
                    <div class="absolute end-0 z-20 mt-2 w-52 rounded-xl bg-white p-2 shadow-lg ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                        <a href="<?= e(url('profile')) ?>" class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800">Your profile</a>
                        <form method="POST" action="<?= e(url('logout')) ?>">
                            <?= csrf_field() ?>
                            <button class="block w-full rounded-lg px-3 py-2 text-start text-sm text-red-600 hover:bg-red-50">Sign out</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-6 lg:p-8">
            <?php $this->include('partials.alerts'); ?>
            <?= $this->yield('content') ?>
        </main>
    </div>
</div>
<!-- Toast region: app.js (window.HalaToast) appends transient notifications here. -->
<div id="toast-region" class="pointer-events-none fixed bottom-4 end-4 z-toast flex flex-col gap-3" aria-live="polite" aria-atomic="false"></div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?= $this->yield('scripts') ?>
</body>
</html>
