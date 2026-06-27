<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<?php
/**
 * Super-Admin Platform Console — one workspace, in depth.
 *
 * @var string               $title
 * @var array<string, mixed> $workspace
 */
$statusVariant = static function (string $key): string {
    return match ($key) {
        'active'             => 'green',
        'trial', 'trialing'  => 'info',
        'suspended'          => 'red',
        'canceled', 'expired', 'past_due' => 'amber',
        default              => 'slate',
    };
};
$dash = static fn (?string $v): string => ($v !== null && $v !== '') ? $v : '—';
$ws = $workspace;
$suspended = ($ws['status_key'] ?? '') === 'suspended';
$sub = $ws['subscription'] ?? null;
?>
<div class="space-y-6">
    <?= component('page-header', [
        'title'      => (string) $ws['name'],
        'subtitle'   => 'Workspace #' . (int) $ws['id'] . ' · ' . (string) $ws['slug'],
        'breadcrumb' => component('breadcrumb', ['items' => [
            ['label' => 'Workspaces', 'href' => url('system/platform/workspaces')],
            ['label' => (string) $ws['name']],
        ]]),
        'actions' => ($suspended
            ? '<form method="POST" action="' . e(url('system/platform/workspaces/activate')) . '">'
                . csrf_field()
                . '<input type="hidden" name="id" value="' . (int) $ws['id'] . '">'
                . component('button', ['label' => 'Activate workspace', 'type' => 'submit', 'variant' => 'success', 'size' => 'sm'])
                . '</form>'
            : component('button', [
                'label'      => 'Suspend workspace',
                'variant'    => 'danger',
                'size'       => 'sm',
                'attributes' => ['data-modal-open' => 'suspend-ws-detail'],
            ])
        ),
    ]) ?>

    <?php $this->include('partials.alerts'); ?>

    <?php if (! $suspended): ?>
        <?= component('modal', [
            'id'     => 'suspend-ws-detail',
            'title'  => 'Suspend workspace?',
            'slot'   => '<p>Suspending <strong>' . e((string) $ws['name'])
                . '</strong> blocks its members from working in it until you re-activate it. This is recorded in the audit log.</p>',
            'footer' => '<form method="POST" action="' . e(url('system/platform/workspaces/suspend')) . '">'
                . csrf_field()
                . '<input type="hidden" name="id" value="' . (int) $ws['id'] . '">'
                . component('button', ['label' => 'Suspend workspace', 'type' => 'submit', 'variant' => 'danger'])
                . '</form>',
        ]) ?>
    <?php endif; ?>

    <!-- Overview -->
    <div class="grid gap-6 lg:grid-cols-3">
        <?php
        $overview = '<dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">'
            . '<div><dt class="text-slate-500 dark:text-slate-400">Status</dt><dd class="mt-1">'
            . component('badge', ['label' => $dash($ws['status_key']), 'variant' => $statusVariant((string) $ws['status_key'])])
            . '</dd></div>'
            . '<div><dt class="text-slate-500 dark:text-slate-400">Created</dt><dd class="mt-1 font-medium text-slate-900 dark:text-white">' . e($dash($ws['created_at'] ?? null)) . '</dd></div>'
            . '<div><dt class="text-slate-500 dark:text-slate-400">Owner</dt><dd class="mt-1 font-medium text-slate-900 dark:text-white">' . e($dash($ws['owner_name'] ?? null)) . '</dd></div>'
            . '<div><dt class="text-slate-500 dark:text-slate-400">Owner email</dt><dd class="mt-1 font-medium text-slate-900 dark:text-white">' . e($dash($ws['owner_email'] ?? null)) . '</dd></div>'
            . '</dl>';
        ?>
        <div class="lg:col-span-2"><?= component('card', ['title' => 'Overview', 'slot' => $overview]) ?></div>

        <?php
        $billing = '<dl class="space-y-3 text-sm">'
            . '<div class="flex items-center justify-between"><dt class="text-slate-500 dark:text-slate-400">Plan</dt><dd class="font-medium text-slate-900 dark:text-white">' . e($dash($sub['plan_name'] ?? null)) . '</dd></div>'
            . '<div class="flex items-center justify-between"><dt class="text-slate-500 dark:text-slate-400">Subscription</dt><dd>'
            . (! empty($sub['status_key'])
                ? component('badge', ['label' => (string) $sub['status_key'], 'variant' => $statusVariant((string) $sub['status_key'])])
                : '<span class="text-slate-400">—</span>')
            . '</dd></div>'
            . '<div class="flex items-center justify-between"><dt class="text-slate-500 dark:text-slate-400">AI keys</dt><dd class="font-medium text-slate-900 dark:text-white">' . e((string) (int) ($ws['ai_key_count'] ?? 0)) . '</dd></div>'
            . '<div class="flex items-center justify-between"><dt class="text-slate-500 dark:text-slate-400">Files</dt><dd class="font-medium text-slate-900 dark:text-white">' . e((string) (int) ($ws['file_count'] ?? 0)) . '</dd></div>'
            . '</dl>';
        ?>
        <div><?= component('card', ['title' => 'Billing & resources', 'slot' => $billing]) ?></div>
    </div>

    <!-- Members -->
    <?php
    $memberRows = [];
    foreach (($ws['members'] ?? []) as $m) {
        $identity = '<div class="leading-tight"><div class="font-medium text-slate-900 dark:text-white">'
            . e($dash($m['user_name'] ?? null))
            . '</div><div class="text-xs text-slate-500 dark:text-slate-400">'
            . e($dash($m['user_email'] ?? null)) . '</div></div>';

        $roles = $m['roles'] ?? [];
        $roleCell = $roles === []
            ? '<span class="text-slate-400">—</span>'
            : implode(' ', array_map(
                static fn (string $r): string => component('badge', ['label' => $r, 'variant' => 'slate', 'class' => 'me-1']),
                $roles
            ));

        $memberRows[] = [
            $identity,
            e($dash($m['title'] ?? null)),
            component('badge', [
                'label'   => $dash($m['status_key'] ?? null),
                'variant' => ($m['status_key'] ?? '') === 'active' ? 'green' : 'slate',
            ]),
            $roleCell,
        ];
    }
    ?>
    <?= component('card', [
        'title' => 'Members (' . count($ws['members'] ?? []) . ')',
        'padded' => false,
        'slot'  => component('table', [
            'columns' => ['Member', 'Title', 'Status', 'Roles'],
            'rows'    => $memberRows,
            'empty'   => 'This workspace has no members.',
        ]),
    ]) ?>

    <!-- Recent activity -->
    <?php
    $activityRows = [];
    foreach (($ws['recent_activity'] ?? []) as $a) {
        $activityRows[] = [
            '<span class="font-mono text-xs text-slate-600 dark:text-slate-300">' . e((string) $a['action']) . '</span>',
            e($dash($a['description'] ?? null)),
            e($dash($a['actor_name'] ?? null)),
            e($dash($a['created_at'] ?? null)),
        ];
    }
    ?>
    <?= component('card', [
        'title' => 'Recent activity',
        'padded' => false,
        'slot'  => component('table', [
            'columns' => ['Action', 'Description', 'Actor', 'When'],
            'rows'    => $activityRows,
            'empty'   => 'No activity recorded for this workspace yet.',
        ]),
    ]) ?>
</div>
<?php $this->endSection(); ?>
