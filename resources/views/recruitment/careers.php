<?php
/** @var array<string,mixed> $company */
/** @var list<array<string,mixed>> $jobs */
/** @var array{employment_type:list<string>,seniority:list<string>,location:list<string>} $facets */
/** @var array{q:string,employment_type:string,location:string} $filters */
/** @var bool $hasLogo */

// Brand accent — only honour a safe hex; otherwise the default azure.
$accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $company['brand_color']) ? (string) $company['brand_color'] : '#2563eb';
$initial = strtoupper(substr((string) ($company['logo_text'] ?: $company['name']), 0, 1));
$slug = (string) $company['slug'];
$hasFilters = $filters['q'] !== '' || $filters['employment_type'] !== '' || $filters['location'] !== '';

// hex → "r, g, b" so we can build subtle tints for the hero.
$rgb = static function (string $hex): string {
    [$r, $g, $b] = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];

    return "{$r}, {$g}, {$b}";
};
$rgbVal = $rgb($accent);

$badge = static function (string $text, string $icon = ''): string {
    return '<span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">' . $icon . e($text) . '</span>';
};
$select = static function (string $name, string $current, array $options, string $anyLabel): string {
    $html = '<select name="' . e($name) . '" class="rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-100">';
    $html .= '<option value="">' . e($anyLabel) . '</option>';
    foreach ($options as $opt) {
        $html .= '<option value="' . e($opt) . '"' . ($current === $opt ? ' selected' : '') . '>' . e($opt) . '</option>';
    }

    return $html . '</select>';
};
?>
<div style="--accent: <?= e($accent) ?>; --accent-rgb: <?= e($rgbVal) ?>;" class="min-h-screen bg-slate-50">

    <!-- ─────────────────────────── Hero ─────────────────────────── -->
    <header class="relative overflow-hidden border-b border-slate-200 bg-white">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-64"
             style="background: radial-gradient(120% 100% at 50% 0%, rgba(var(--accent-rgb), 0.10) 0%, rgba(var(--accent-rgb), 0.03) 45%, transparent 75%);"></div>
        <div class="relative mx-auto max-w-6xl px-6 pt-16 pb-12 sm:pt-20">
            <div class="flex flex-col items-center text-center">
                <div class="flex h-24 w-24 items-center justify-center overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm ring-1 ring-black/5">
                    <?php if ($hasLogo): ?>
                        <img src="/view/<?= e($slug) ?>/logo" alt="<?= e($company['name']) ?> logo" class="h-full w-full object-contain p-2">
                    <?php else: ?>
                        <span class="flex h-full w-full items-center justify-center text-4xl font-bold text-white" style="background: var(--accent);"><?= e($initial) ?></span>
                    <?php endif; ?>
                </div>

                <h1 class="mt-6 text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl"><?= e($company['name']) ?></h1>
                <?php if ($company['tagline'] !== ''): ?>
                    <p class="mt-3 max-w-2xl text-lg leading-relaxed text-slate-600"><?= e($company['tagline']) ?></p>
                <?php endif; ?>

                <div class="mt-5 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-sm text-slate-500">
                    <?php if ($company['industry'] !== ''): ?>
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
                            <?= e($company['industry']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($company['website'] !== ''): ?>
                        <a href="<?= e($company['website']) ?>" target="_blank" rel="noopener nofollow" class="inline-flex items-center gap-1.5 font-medium hover:underline" style="color: var(--accent);">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918"/></svg>
                            Website
                        </a>
                    <?php endif; ?>
                    <?php if ($company['contact_email'] !== ''): ?>
                        <a href="mailto:<?= e($company['contact_email']) ?>" class="inline-flex items-center gap-1.5 hover:text-slate-700">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                            Contact
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($company['about'] !== ''): ?>
                    <p class="mt-6 max-w-3xl whitespace-pre-line text-[15px] leading-7 text-slate-600"><?= e($company['about']) ?></p>
                <?php endif; ?>

                <a href="#openings" class="mt-8 inline-flex items-center gap-2 rounded-full px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-90" style="background: var(--accent);">
                    <?= count($jobs) ?> open role<?= count($jobs) === 1 ? '' : 's' ?>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                </a>
            </div>
        </div>
    </header>

    <!-- ─────────────────────── Open positions ───────────────────── -->
    <main id="openings" class="mx-auto max-w-6xl px-6 py-14">
        <div class="mb-8 text-center">
            <h2 class="text-2xl font-bold tracking-tight text-slate-900">Open positions</h2>
            <p class="mt-1 text-sm text-slate-500"><?= count($jobs) ?> role<?= count($jobs) === 1 ? '' : 's' ?><?= $hasFilters ? ' matching your search' : ' — find where you fit' ?>.</p>
        </div>

        <!-- Sticky search/filter bar -->
        <form method="get" action="/view/<?= e($slug) ?>" class="sticky top-4 z-10 mx-auto mb-8 flex max-w-3xl flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white/90 p-2.5 shadow-sm backdrop-blur">
            <div class="relative min-w-[12rem] flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                <input name="q" value="<?= e($filters['q']) ?>" placeholder="Search roles, teams, keywords…" class="w-full rounded-xl border border-transparent bg-slate-50 py-2.5 pl-9 pr-3 text-sm text-slate-700 focus:border-slate-300 focus:bg-white focus:outline-none">
            </div>
            <?php if ($facets['employment_type'] !== []): ?><?= $select('employment_type', $filters['employment_type'], $facets['employment_type'], 'Any type') ?><?php endif; ?>
            <?php if ($facets['location'] !== []): ?><?= $select('location', $filters['location'], $facets['location'], 'Any location') ?><?php endif; ?>
            <button class="rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90" style="background: var(--accent);">Search</button>
            <?php if ($hasFilters): ?><a href="/view/<?= e($slug) ?>" class="px-2 text-sm text-slate-500 hover:text-slate-700">Clear</a><?php endif; ?>
        </form>

        <?php if ($jobs === []): ?>
            <div class="mx-auto max-w-xl rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1.3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.073a2.25 2.25 0 01-1.632 2.163l-1.32.377a9.04 9.04 0 01-2.496.35H6.75a2.25 2.25 0 01-2.25-2.25v-4.073M20.25 14.15A2.25 2.25 0 0021 12.265V8.706a2.25 2.25 0 00-1.591-2.153l-6-1.8a2.25 2.25 0 00-1.318 0l-6 1.8A2.25 2.25 0 003 8.706v3.559c0 .98.626 1.851 1.5 2.164m15.75-.279a48.07 48.07 0 00-7.5-.529c-2.553 0-5.06.18-7.5.529m0 0V6.75"/></svg>
                <p class="mt-4 text-base font-semibold text-slate-700"><?= $hasFilters ? 'No roles match your search.' : 'No open positions right now.' ?></p>
                <p class="mt-1 text-sm text-slate-400"><?= $hasFilters ? 'Try clearing the filters.' : 'Check back soon — new roles are posted here.' ?></p>
            </div>
        <?php else: ?>
            <div class="grid gap-4 sm:grid-cols-2">
                <?php foreach ($jobs as $job): ?>
                    <?php $dl = ! empty($job['deadline_at']) ? strtotime((string) $job['deadline_at'] . ' UTC') : null; ?>
                    <a href="/jobs/public/<?= e($job['public_token']) ?>" class="group flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-lg">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="text-lg font-semibold leading-snug text-slate-900"><?= e($job['title']) ?></h3>
                            <span class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-50 text-slate-400 transition group-hover:text-white" style="--tw-bg-opacity:1;" onmouseover="this.style.background='var(--accent)'" onmouseout="this.style.background=''">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                            </span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <?php if (! empty($job['location'])): ?><?= $badge((string) $job['location']) ?><?php endif; ?>
                            <?php if (! empty($job['employment_type'])): ?><?= $badge((string) $job['employment_type']) ?><?php endif; ?>
                            <?php if (! empty($job['seniority'])): ?><?= $badge((string) $job['seniority']) ?><?php endif; ?>
                        </div>
                        <?php if (! empty($job['description'])): ?>
                            <p class="mt-4 line-clamp-3 text-sm leading-6 text-slate-500"><?= e(mb_substr((string) $job['description'], 0, 220)) ?><?= mb_strlen((string) $job['description']) > 220 ? '…' : '' ?></p>
                        <?php endif; ?>
                        <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4">
                            <span class="text-sm font-semibold" style="color: var(--accent);">View &amp; apply</span>
                            <?php if ($dl !== null && $dl > time()): ?>
                                <span class="text-xs font-medium text-amber-600">Closes <?= e(gmdate('M j', $dl)) ?></span>
                            <?php elseif ($dl !== null): ?>
                                <span class="text-xs font-medium text-slate-400">Closed</span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- ─────────────────────────── Footer ───────────────────────── -->
    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-6xl px-6 py-10">
            <div class="flex flex-col items-center gap-3 text-center sm:flex-row sm:justify-between sm:text-left">
                <div class="text-sm text-slate-500">
                    <div class="font-semibold text-slate-700"><?= e($company['legal_name'] !== '' ? (string) $company['legal_name'] : (string) $company['name']) ?></div>
                    <?php if ($company['address'] !== ''): ?><div class="mt-0.5 whitespace-pre-line text-xs text-slate-400"><?= e($company['address']) ?></div><?php endif; ?>
                </div>
                <div class="flex flex-wrap items-center gap-4 text-xs text-slate-400">
                    <?php if ($company['website'] !== ''): ?><a href="<?= e($company['website']) ?>" target="_blank" rel="noopener nofollow" class="hover:text-slate-600">Website</a><?php endif; ?>
                    <?php if ($company['terms_url'] !== ''): ?><a href="<?= e($company['terms_url']) ?>" target="_blank" rel="noopener nofollow" class="hover:text-slate-600">Terms</a><?php endif; ?>
                    <?php if ($company['privacy_url'] !== ''): ?><a href="<?= e($company['privacy_url']) ?>" target="_blank" rel="noopener nofollow" class="hover:text-slate-600">Privacy</a><?php endif; ?>
                </div>
            </div>
        </div>
    </footer>
</div>
