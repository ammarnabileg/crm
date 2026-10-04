<?php
/**
 * Reusable First Impression report renderer (read-only breakdown).
 * Expects in scope:
 *   @var array<string,mixed> $full      from FirstImpressionReportService::full()
 *   @var bool                $readonly  true on the candidate's own page
 *
 * Everything here is presentation-only; the data is already computed & stored.
 */
$report = $full['report'] ?? [];
$analysis = $full['analysis'] ?? null;
$details = $full['details'] ?? [];
$social = $full['social'] ?? null;
$snapshots = $full['snapshots'] ?? [];

$overall = (int) ($report['overall_score'] ?? 0);
$passed = (int) ($report['passed'] ?? 0) === 1;
$overridden = (int) ($report['overridden'] ?? 0) === 1;

$scoreColor = static fn (int $v): string => $v >= 80 ? 'emerald' : ($v >= 65 ? 'indigo' : ($v >= 45 ? 'amber' : 'rose'));
$c = $scoreColor($overall);

$bar = static function (string $label, $value) use ($scoreColor): string {
    $v = (int) $value;
    $col = $scoreColor($v);

    return '<div class="mb-2">'
        . '<div class="flex justify-between text-xs"><span class="text-slate-600">' . e($label) . '</span><span class="font-semibold text-slate-800">' . $v . '%</span></div>'
        . '<div class="mt-1 h-1.5 w-full rounded-full bg-slate-100"><div class="h-1.5 rounded-full bg-' . $col . '-500" style="width:' . max(2, min(100, $v)) . '%"></div></div>'
        . '</div>';
};
$chips = static function (string $kind, string $color) use ($details): string {
    $items = $details[$kind] ?? [];
    if ($items === []) {
        return '<span class="text-xs text-slate-400">—</span>';
    }
    $out = '';
    foreach ($items as $it) {
        $out .= '<span class="mr-1 mb-1 inline-block rounded-full bg-' . $color . '-50 px-2.5 py-0.5 text-xs text-' . $color . '-700">' . e((string) $it['label']) . '</span>';
    }

    return $out;
};
$list = static function (string $kind) use ($details): string {
    $items = $details[$kind] ?? [];
    if ($items === []) {
        return '<li class="text-xs text-slate-400">—</li>';
    }
    $out = '';
    foreach ($items as $it) {
        $out .= '<li class="text-xs text-slate-600">' . e((string) $it['label']) . '</li>';
    }

    return $out;
};
?>
<div class="space-y-5">
    <!-- Headline -->
    <div class="flex flex-wrap items-center gap-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-<?= $c ?>-50 text-<?= $c ?>-600">
            <span class="text-2xl font-bold"><?= $overall ?></span>
        </div>
        <div class="min-w-0 grow">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-semibold text-slate-900">First Impression Score</span>
                <?php if ($overridden): ?>
                    <span class="rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-700">Overridden — allowed to AI</span>
                <?php elseif ($passed): ?>
                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Passed → AI interview</span>
                <?php else: ?>
                    <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700">Filtered before AI</span>
                <?php endif; ?>
            </div>
            <p class="mt-1 text-xs text-slate-500">
                Threshold for this role: <span class="font-medium text-slate-700"><?= (int) ($report['threshold'] ?? 0) ?>%</span>
                · Confidence: <span class="font-medium text-slate-700"><?= (int) ($report['confidence'] ?? 0) ?>%</span>
                · Computed with no AI &amp; no credits.
            </p>
            <div class="mt-2 flex flex-wrap gap-4 text-xs text-slate-600">
                <span>CV / job fit (core): <span class="font-semibold text-slate-800"><?= (int) ($report['resume_score'] ?? 0) ?>%</span></span>
                <span>Job relevance: <span class="font-semibold text-slate-800"><?= (int) ($report['job_match_score'] ?? 0) ?>%</span></span>
                <span>Social boost:
                    <span class="font-semibold text-slate-800"><?php $b = (int) ($report['social_boost'] ?? 0); echo ($b > 0 ? '+' : '') . $b; ?></span>
                    <?php if (($report['social_score'] ?? null) === null): ?><span class="text-slate-400">(no social — neutral)</span><?php endif; ?>
                </span>
            </div>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <!-- Sub-scores -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="mb-3 text-sm font-semibold text-slate-900">Résumé analysis</h3>
            <?php if ($analysis !== null): ?>
                <?= $bar('Skill match', $analysis['skill_match'] ?? 0) ?>
                <?= $bar('Experience match', $analysis['experience_match'] ?? 0) ?>
                <?= $bar('Seniority fit', $analysis['seniority_match'] ?? 0) ?>
                <?= $bar('Keyword relevance', $analysis['keyword_density'] ?? 0) ?>
                <?= $bar('Education fit', $analysis['education_match'] ?? 0) ?>
                <?= $bar('Language match', $analysis['language_match'] ?? 0) ?>
                <?= $bar('Completeness', $analysis['completeness'] ?? 0) ?>
                <?= $bar('Formatting quality', $analysis['formatting_quality'] ?? 0) ?>
                <?= $bar('Employment stability', $analysis['employment_stability'] ?? 0) ?>
                <p class="mt-2 text-xs text-slate-400">
                    Parsed via <?= e((string) ($analysis['parser'] ?? 'n/a')) ?> (confidence <?= (int) ($analysis['parse_confidence'] ?? 0) ?>%)
                    <?php if (($analysis['years_experience'] ?? null) !== null): ?> · <?= (int) $analysis['years_experience'] ?> yrs experience detected<?php endif; ?>
                </p>
            <?php else: ?>
                <p class="text-xs text-slate-400">No résumé analysis recorded.</p>
            <?php endif; ?>
        </div>

        <!-- Skills + evidence -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="mb-2 text-sm font-semibold text-slate-900">Skills</h3>
            <p class="text-xs font-medium text-slate-500">Matched</p>
            <div class="mt-1"><?= $chips('skill_matched', 'emerald') ?></div>
            <p class="mt-3 text-xs font-medium text-slate-500">Missing</p>
            <div class="mt-1"><?= $chips('skill_missing', 'rose') ?></div>
            <p class="mt-3 text-xs font-medium text-slate-500">Keyword hits</p>
            <div class="mt-1"><?= $chips('keyword_hit', 'indigo') ?></div>
            <p class="mt-3 text-xs font-medium text-slate-500">Missing documents / sections</p>
            <div class="mt-1"><?= $chips('section_missing', 'amber') ?></div>
        </div>

        <!-- Strengths / weaknesses -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="mb-2 text-sm font-semibold text-slate-900">Strengths</h3>
            <ul class="list-disc space-y-1 pl-4"><?= $list('strength') ?></ul>
            <h3 class="mb-2 mt-4 text-sm font-semibold text-slate-900">Weaknesses</h3>
            <ul class="list-disc space-y-1 pl-4"><?= $list('weakness') ?></ul>
        </div>

        <!-- Recommendations + rules -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="mb-2 text-sm font-semibold text-slate-900">Recommendations</h3>
            <ul class="list-disc space-y-1 pl-4"><?= $list('recommendation') ?></ul>
            <div class="mt-4 grid grid-cols-2 gap-3">
                <div>
                    <p class="text-xs font-medium text-emerald-600">Rules passed</p>
                    <ul class="mt-1 space-y-1"><?= $list('rule_match') ?></ul>
                </div>
                <div>
                    <p class="text-xs font-medium text-rose-600">Rules failed</p>
                    <ul class="mt-1 space-y-1"><?= $list('rule_fail') ?></ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Candidate Intelligence (rule-based, zero-AI, advisory) -->
    <?php
    $insightKinds = ['insight_seniority', 'insight_progression', 'insight_stability', 'insight_primary_stack',
        'insight_stack', 'insight_industry', 'insight_leadership', 'insight_skill_gap', 'insight_consistency', 'insight_highlight'];
    $hasInsights = false;
    foreach ($insightKinds as $ik) {
        if (! empty($details[$ik])) { $hasInsights = true; break; }
    }
    ?>
    <?php if ($hasInsights): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="mb-1 text-sm font-semibold text-slate-900">Candidate intelligence
                <span class="text-xs font-normal text-slate-400">(rule-based · no AI · advisory — does not change the score)</span>
            </h3>
            <div class="mt-3 grid gap-4 md:grid-cols-2">
                <?php if (! empty($details['insight_highlight'])): ?>
                    <div class="md:col-span-2">
                        <p class="text-xs font-medium text-slate-500">At a glance</p>
                        <div class="mt-1"><?= $chips('insight_highlight', 'indigo') ?></div>
                    </div>
                <?php endif; ?>
                <?php
                $insightRow = static function (string $kind, string $title, string $color) use ($details, $chips): string {
                    if (empty($details[$kind])) {
                        return '';
                    }

                    return '<div><p class="text-xs font-medium text-slate-500">' . e($title) . '</p>'
                        . '<div class="mt-1">' . $chips($kind, $color) . '</div></div>';
                };
                echo $insightRow('insight_seniority', 'Seniority', 'slate');
                echo $insightRow('insight_progression', 'Career progression', 'emerald');
                echo $insightRow('insight_stability', 'Employment stability', 'slate');
                echo $insightRow('insight_stack', 'Technical stacks', 'indigo');
                echo $insightRow('insight_industry', 'Industry experience', 'violet');
                echo $insightRow('insight_leadership', 'Leadership indicators', 'amber');
                echo $insightRow('insight_portfolio', 'Portfolio', 'emerald');
                echo $insightRow('insight_resume_quality', 'Résumé quality', 'slate');
                echo $insightRow('insight_skill_gap', 'Skill gaps vs the role', 'rose');
                ?>
            </div>
            <?php if (! empty($details['insight_consistency'])): ?>
                <div class="mt-4 rounded-xl border border-amber-100 bg-amber-50/60 p-3">
                    <p class="text-xs font-medium text-amber-700">Consistency notes (advisory — worth confirming)</p>
                    <ul class="mt-1 list-disc space-y-1 pl-4"><?= $list('insight_consistency') ?></ul>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Social credibility -->
    <?php if ($social !== null): ?>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="text-sm font-semibold text-slate-900">Social credibility <span class="text-xs font-normal text-slate-400">(optional · 30% weight)</span></h3>
                <span class="text-xs text-slate-500">
                    Score: <span class="font-semibold text-slate-800"><?= $social['score'] === null ? '—' : (int) $social['score'] ?></span>
                    · Boost applied: <span class="font-semibold text-slate-800"><?php $b = (int) ($social['boost'] ?? 0); echo ($b > 0 ? '+' : '') . $b; ?></span>
                    · <?= (int) ($social['sources_reachable'] ?? 0) ?>/<?= (int) ($social['sources_total'] ?? 0) ?> sources read
                </span>
            </div>
            <div class="mt-3 space-y-2">
                <?php foreach ($snapshots as $sn): ?>
                    <div class="rounded-xl border border-slate-100 p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="min-w-0">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500"><?= e((string) $sn['platform']) ?></span>
                                <a href="<?= e((string) $sn['url']) ?>" target="_blank" rel="noopener nofollow" class="ml-2 text-xs text-indigo-600 hover:underline"><?= e(mb_strimwidth((string) $sn['url'], 0, 48, '…')) ?></a>
                            </div>
                            <span class="shrink-0 text-xs">
                                <?php if ($sn['score'] === null): ?>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-slate-500">neutral</span>
                                <?php else: $sv = (int) $sn['score']; ?>
                                    <span class="rounded-full px-2 py-0.5 <?= $sv > 0 ? 'bg-emerald-50 text-emerald-700' : ($sv < 0 ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-500') ?>"><?= ($sv > 0 ? '+' : '') . $sv ?></span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if (! empty($sn['summary'])): ?><p class="mt-1 text-xs text-slate-600"><?= e((string) $sn['summary']) ?></p><?php endif; ?>
                        <?php if (! empty($sn['signals'])): ?>
                            <div class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-[11px] text-slate-400">
                                <?php foreach ($sn['signals'] as $sig): ?>
                                    <?php $v = $sig['numeric_value'] ?? $sig['string_value']; if ($v === null || $v === '') { continue; } ?>
                                    <span><?= e((string) $sig['signal_key']) ?>: <span class="text-slate-600"><?= e((string) $v) ?></span></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="mt-2 text-[11px] text-slate-400">Public data only. Missing or unreachable profiles are neutral and never reduce the score.</p>
        </div>
    <?php endif; ?>
</div>
