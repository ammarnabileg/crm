<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<?php
/**
 * Global Search — keyword over the tenant's jobs + applications, plus a talent
 * filter (skills / experience / city) over the candidate pool via AdvancedSearch.
 *
 * @var string $q
 * @var array<string,mixed> $filters
 * @var array{jobs:array<int,array<string,mixed>>, applications:array<int,array<string,mixed>>, candidates:array<int,array<string,mixed>>} $results
 * @var bool $searched
 */
$jobs = $results['jobs'] ?? [];
$applications = $results['applications'] ?? [];
$candidates = $results['candidates'] ?? [];
$total = count($jobs) + count($applications) + count($candidates);

$scoreBadge = static function ($score): string {
    if ($score === null || $score === '') {
        return '<span class="text-xs text-slate-400">—</span>';
    }
    $s = (float) $score;
    $variant = $s >= 75 ? 'green' : ($s >= 50 ? 'amber' : 'slate');
    return component('badge', ['label' => rtrim(rtrim(number_format($s, 1), '0'), '.'), 'variant' => $variant]);
};
?>
<?= component('page-header', [
    'title'    => 'Search',
    'subtitle' => 'Find jobs and applications by keyword, or filter the talent pool.',
]) ?>

<?= component('card', ['class' => 'mb-6', 'slot' => '
    <form method="GET" action="' . e(url('search')) . '" class="space-y-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label for="q" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Keyword</label>
                ' . component('input', ['name' => 'q', 'id' => 'q', 'value' => $q, 'placeholder' => 'Job title, candidate name or email…']) . '
            </div>
            <div>' . component('button', ['label' => 'Search', 'type' => 'submit', 'variant' => 'primary']) . '</div>
        </div>
        <details class="rounded-lg border border-slate-200 p-3 dark:border-slate-800"' . ($filters !== [] ? ' open' : '') . '>
            <summary class="cursor-pointer text-sm font-medium text-slate-600 dark:text-slate-300">Talent filters (candidate pool)</summary>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label for="skills" class="mb-1 block text-xs font-medium text-slate-500">Skills (comma-separated)</label>
                    ' . component('input', ['name' => 'skills', 'id' => 'skills', 'value' => is_array($filters['skills'] ?? null) ? implode(', ', $filters['skills']) : (string) ($filters['skills'] ?? ''), 'placeholder' => 'PHP, MySQL']) . '
                </div>
                <div>
                    <label for="min_experience" class="mb-1 block text-xs font-medium text-slate-500">Min experience (yrs)</label>
                    ' . component('input', ['name' => 'min_experience', 'id' => 'min_experience', 'type' => 'number', 'value' => (string) ($filters['min_experience'] ?? ''), 'attributes' => ['min' => '0', 'step' => '0.5']]) . '
                </div>
                <div>
                    <label for="city" class="mb-1 block text-xs font-medium text-slate-500">City</label>
                    ' . component('input', ['name' => 'city', 'id' => 'city', 'value' => (string) ($filters['city'] ?? ''), 'placeholder' => 'Riyadh']) . '
                </div>
                <div>
                    <label for="max_expected_salary" class="mb-1 block text-xs font-medium text-slate-500">Max expected salary</label>
                    ' . component('input', ['name' => 'max_expected_salary', 'id' => 'max_expected_salary', 'type' => 'number', 'value' => (string) ($filters['max_expected_salary'] ?? ''), 'attributes' => ['min' => '0']]) . '
                </div>
            </div>
        </details>
    </form>
']) ?>

<?php if (! $searched): ?>
    <?= component('state', [
        'variant' => 'empty',
        'title'   => 'Search the workspace',
        'message' => 'Enter a keyword to find jobs and applications, or use the talent filters to search the candidate pool.',
    ]) ?>
<?php elseif ($total === 0): ?>
    <?= component('state', [
        'variant' => 'empty',
        'title'   => 'No matches',
        'message' => 'Nothing matched your search. Try a different keyword or relax the talent filters.',
    ]) ?>
<?php else: ?>
    <?php
    // --- Jobs ---------------------------------------------------------------
    if ($jobs !== []):
        $jobRows = [];
        foreach ($jobs as $j) {
            $jobRows[] = [
                '<a class="font-medium text-brand-600 hover:underline dark:text-brand-300" href="' . e(url('jobs')) . '">' . e((string) ($j['title'] ?? '')) . '</a>',
                '<span class="text-xs text-slate-400">' . e((string) ($j['slug'] ?? '')) . '</span>',
            ];
        }
        echo '<h2 class="mb-2 mt-2 text-sm font-semibold uppercase tracking-wide text-slate-500">Jobs (' . count($jobs) . ')</h2>';
        echo component('table', ['columns' => ['Title', 'Slug'], 'rows' => $jobRows, 'class' => 'mb-6']);
    endif;

    // --- Applications -------------------------------------------------------
    if ($applications !== []):
        $appRows = [];
        foreach ($applications as $a) {
            $appRows[] = [
                '<a class="font-medium text-brand-600 hover:underline dark:text-brand-300" href="' . e(url('applications/show?id=' . (int) $a['id'])) . '">' . e((string) ($a['candidate_name'] ?? 'Candidate')) . '</a>'
                    . '<div class="text-xs text-slate-400">' . e((string) ($a['candidate_email'] ?? '')) . '</div>',
                e((string) ($a['job_title'] ?? '')),
                $scoreBadge($a['score'] ?? null),
            ];
        }
        echo '<h2 class="mb-2 mt-2 text-sm font-semibold uppercase tracking-wide text-slate-500">Applications (' . count($applications) . ')</h2>';
        echo component('table', ['columns' => ['Candidate', 'Job', ['label' => 'Score', 'align' => 'end']], 'rows' => $appRows, 'class' => 'mb-6']);
    endif;

    // --- Candidates (talent pool) ------------------------------------------
    if ($candidates !== []):
        $candRows = [];
        foreach ($candidates as $c) {
            $exp = $c['total_experience_years'] ?? null;
            $candRows[] = [
                '<span class="font-medium text-slate-800 dark:text-slate-100">' . e((string) ($c['candidate_name'] ?? 'Candidate')) . '</span>'
                    . '<div class="text-xs text-slate-400">' . e((string) ($c['candidate_email'] ?? '')) . '</div>',
                e((string) ($c['city'] ?? '—')),
                $exp === null ? '<span class="text-xs text-slate-400">—</span>' : e(rtrim(rtrim(number_format((float) $exp, 1), '0'), '.') . ' yrs'),
            ];
        }
        echo '<h2 class="mb-2 mt-2 text-sm font-semibold uppercase tracking-wide text-slate-500">Candidates (' . count($candidates) . ')</h2>';
        echo component('table', ['columns' => ['Name', 'City', ['label' => 'Experience', 'align' => 'end']], 'rows' => $candRows]);
    endif;
    ?>
<?php endif; ?>
<?php $this->endSection(); ?>
