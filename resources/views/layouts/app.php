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
    ['members',   'Members',            'members.view', false],
    ['roles',     'Roles & Permissions', 'roles.view',  false],
    ['ai',        'AI Settings',        'ai.view',      false],
    ['billing',   'Billing',            'billing.view', false],
    ['settings',  'Workspace Settings',   'settings.view', false],
];
$isActive = fn (string $path): bool => $current === '/' . trim($path, '/') || str_starts_with($current, '/' . trim($path, '/') . '/');
?>
<!DOCTYPE html>
<html lang="<?= e(locale()) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e(($title ?? 'Dashboard') . ' · ' . config('app.name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body class="min-h-screen bg-slate-50">
<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="hidden w-64 shrink-0 flex-col border-e border-slate-200 bg-white lg:flex">
        <div class="flex h-16 items-center gap-2 border-b border-slate-200 px-5">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-600 font-bold text-white">H</span>
            <span class="font-bold text-slate-900"><?= e(config('app.name')) ?></span>
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
        <header class="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-6">
            <div class="min-w-0">
                <?php if ($workspace): ?>
                    <details class="relative">
                        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-100">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-100 text-sm font-semibold text-brand-700"><?= e(mb_substr($workspace->name, 0, 1)) ?></span>
                            <span class="truncate font-semibold text-slate-800"><?= e($workspace->name) ?></span>
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </summary>
                        <div class="absolute z-20 mt-2 w-64 rounded-xl bg-white p-2 shadow-lg ring-1 ring-slate-200">
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

            <div class="flex items-center gap-2">
                <a href="<?= e(url(request()->path() === '/' ? '/' : ltrim($current, '/')) . '?lang=' . (is_rtl() ? 'en' : 'ar')) ?>"
                   class="btn-ghost px-2 py-1.5 text-sm"><?= is_rtl() ? 'EN' : 'ع' ?></a>

                <details class="relative">
                    <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-100">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-600 text-sm font-semibold text-white"><?= e($user?->initials()) ?></span>
                        <span class="hidden text-sm font-medium text-slate-700 sm:block"><?= e($user?->name) ?></span>
                    </summary>
                    <div class="absolute end-0 z-20 mt-2 w-52 rounded-xl bg-white p-2 shadow-lg ring-1 ring-slate-200">
                        <a href="<?= e(url('profile')) ?>" class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Your profile</a>
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
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?= $this->yield('scripts') ?>
</body>
</html>
