<?php
/** @var array<string,mixed> $company */
/** @var list<array<string,mixed>> $jobs */
/** @var array{employment_type:list<string>,seniority:list<string>,location:list<string>} $facets */
/** @var array{q:string,employment_type:string,location:string} $filters */
/** @var bool $hasLogo */

// Use the tenant's brand colour as the page accent only when it is a safe hex
// value; otherwise fall back to the default azure accent.
$accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $company['brand_color']) ? (string) $company['brand_color'] : '#2563eb';
$initial = strtoupper(substr((string) ($company['logo_text'] ?: $company['name']), 0, 1));
$slug = (string) $company['slug'];
$hasFilters = $filters['q'] !== '' || $filters['employment_type'] !== '' || $filters['location'] !== '';

$badge = static function (string $text): string {
    return '<span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">' . e($text) . '</span>';
};
$select = static function (string $name, string $current, array $options, string $anyLabel): string {
    $html = '<select name="' . e($name) . '" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none">';
    $html .= '<option value="">' . e($anyLabel) . '</option>';
    foreach ($options as $opt) {
        $sel = $current === $opt ? ' selected' : '';
        $html .= '<option value="' . e($opt) . '"' . $sel . '>' . e($opt) . '</option>';
    }

    return $html . '</select>';
};
?>
<!-- Company hero -->
<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto max-w-5xl px-6 py-10 sm:py-14">
        <div class="flex flex-col items-start gap-5 sm:flex-row sm:items-center">
            <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                <?php if ($hasLogo): ?>
                    <img src="/view/<?= e($slug) ?>/logo" alt="<?= e($company['name']) ?> logo" class="h-full w-full object-contain">
                <?php else: ?>
                    <span class="text-3xl font-bold text-white" style="background:<?= e($accent) ?>;width:100%;height:100%;display:flex;align-items:center;justify-content:center;"><?= e($initial) ?></span>
                <?php endif; ?>
            </div>
            <div class="min-w-0">
                <h1 class="text-3xl font-bold tracking-tight text-slate-900"><?= e($company['name']) ?></h1>
                <?php if ($company['tagline'] !== ''): ?>
                    <p class="mt-1 text-lg text-slate-600"><?= e($company['tagline']) ?></p>
                <?php endif; ?>
                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-slate-500">
                    <?php if ($company['industry'] !== ''): ?>
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
                            <?= e($company['industry']) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($company['website'] !== ''): ?>
                        <a href="<?= e($company['website']) ?>" target="_blank" rel="noopener nofollow" class="inline-flex items-center gap-1.5 font-medium text-indigo-600 hover:underline">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"/></svg>
                            Website
                        </a>
                    <?php endif; ?>
                    <?php if ($company['contact_email'] !== ''): ?>
                        <a href="mailto:<?= e($company['contact_email']) ?>" class="inline-flex items-center gap-1.5 hover:text-slate-700">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                            <?= e($company['contact_email']) ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php if ($company['about'] !== ''): ?>
            <div class="mt-6 max-w-3xl whitespace-pre-line text-sm leading-relaxed text-slate-600"><?= e($company['about']) ?></div>
        <?php endif; ?>
    </div>
</header>

<!-- Openings -->
<div class="mx-auto max-w-5xl px-6 py-8">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-semibold text-slate-900">Open positions</h2>
            <p class="mt-0.5 text-sm text-slate-500"><?= count($jobs) ?> open role<?= count($jobs) === 1 ? '' : 's' ?><?= $hasFilters ? ' matching your filters' : '' ?>.</p>
        </div>
    </div>

    <form method="get" action="/view/<?= e($slug) ?>" class="mb-6 flex flex-wrap items-center gap-2">
        <input name="q" value="<?= e($filters['q']) ?>" placeholder="Search roles…" class="min-w-[12rem] flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
        <?php if ($facets['employment_type'] !== []): ?><?= $select('employment_type', $filters['employment_type'], $facets['employment_type'], 'Any type') ?><?php endif; ?>
        <?php if ($facets['location'] !== []): ?><?= $select('location', $filters['location'], $facets['location'], 'Any location') ?><?php endif; ?>
        <button class="rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background:<?= e($accent) ?>">Search</button>
        <?php if ($hasFilters): ?><a href="/view/<?= e($slug) ?>" class="text-sm text-slate-500 hover:text-slate-700">Clear</a><?php endif; ?>
    </form>

    <div class="space-y-3">
        <?php foreach ($jobs as $job): ?>
            <a href="/jobs/public/<?= e($job['public_token']) ?>" class="block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow-md">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-slate-900"><?= e($job['title']) ?></h3>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <?php if (! empty($job['location'])): ?><?= $badge((string) $job['location']) ?><?php endif; ?>
                            <?php if (! empty($job['employment_type'])): ?><?= $badge((string) $job['employment_type']) ?><?php endif; ?>
                            <?php if (! empty($job['seniority'])): ?><?= $badge((string) $job['seniority']) ?><?php endif; ?>
                        </div>
                        <?php if (! empty($job['description'])): ?>
                            <p class="mt-3 line-clamp-2 max-w-2xl text-sm text-slate-500"><?= e(mb_substr((string) $job['description'], 0, 180)) ?><?= mb_strlen((string) $job['description']) > 180 ? '…' : '' ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold" style="color:<?= e($accent) ?>">
                        View &amp; apply
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </span>
                </div>
            </a>
        <?php endforeach; ?>
        <?php if ($jobs === []): ?>
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1.4" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.073a2.25 2.25 0 01-1.632 2.163l-1.32.377a9.04 9.04 0 01-2.496.35H6.75a2.25 2.25 0 01-2.25-2.25v-4.073M20.25 14.15A2.25 2.25 0 0021 12.265V8.706a2.25 2.25 0 00-1.591-2.153l-6-1.8a2.25 2.25 0 00-1.318 0l-6 1.8A2.25 2.25 0 003 8.706v3.559c0 .98.626 1.851 1.5 2.164m15.75-.279a48.07 48.07 0 00-7.5-.529c-2.553 0-5.06.18-7.5.529m0 0V6.75"/></svg>
                <p class="mt-3 text-sm font-medium text-slate-600"><?= $hasFilters ? 'No roles match your filters.' : 'No open positions right now.' ?></p>
                <p class="mt-1 text-sm text-slate-400"><?= $hasFilters ? 'Try clearing the filters.' : 'Check back soon — new roles are posted here.' ?></p>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($company['legal_name'] !== '' || $company['terms_url'] !== '' || $company['privacy_url'] !== '' || $company['address'] !== ''): ?>
        <div class="mt-10 border-t border-slate-200 pt-6 text-xs text-slate-400">
            <?php if ($company['legal_name'] !== ''): ?><div><?= e($company['legal_name']) ?></div><?php endif; ?>
            <?php if ($company['address'] !== ''): ?><div class="mt-0.5 whitespace-pre-line"><?= e($company['address']) ?></div><?php endif; ?>
            <div class="mt-2 flex flex-wrap gap-4">
                <?php if ($company['terms_url'] !== ''): ?><a href="<?= e($company['terms_url']) ?>" target="_blank" rel="noopener nofollow" class="hover:text-slate-600">Terms</a><?php endif; ?>
                <?php if ($company['privacy_url'] !== ''): ?><a href="<?= e($company['privacy_url']) ?>" target="_blank" rel="noopener nofollow" class="hover:text-slate-600">Privacy</a><?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
