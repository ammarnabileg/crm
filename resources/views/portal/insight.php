<?php
/** @var array<string,mixed> $full */
/** @var string $workspaceName */
$report = $full['report'] ?? [];
$readonly = true;
?>
<div class="mx-auto max-w-4xl">
    <a href="/my-insights" class="text-xs text-slate-400 hover:text-slate-600">← Back to my insights</a>
    <div class="mt-2 mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">First Impression report</h1>
            <p class="mt-1 text-sm text-slate-500">Generated when you applied · <?= e(date('M j, Y', strtotime((string) ($report['created_at'] ?? 'now')))) ?></p>
        </div>
    </div>

    <div class="mb-4 rounded-lg bg-slate-50 px-4 py-3 text-xs text-slate-500">
        This is your automated, no-AI first-impression analysis. It is <strong>read-only</strong> and is
        <strong>recalculated every time you apply to a new job</strong>, because it measures your fit for that specific role.
    </div>

    <?php include __DIR__ . '/../recruitment/_first_impression.php'; ?>
</div>
