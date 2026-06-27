<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<?php
/**
 * Super-Admin Platform Console — every workspace on the platform.
 *
 * @var string                                 $title
 * @var array<int, array<string, mixed>>       $workspaces
 * @var array<string, int>                     $stats
 * @var string                                 $keyword
 * @var int                                    $cap
 */
// Lifecycle status -> badge variant. Unknown keys fall through to slate so a new
// status never renders as a confident green.
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
?>
<div class="space-y-6">
    <?= component('page-header', [
        'title'    => 'Platform · Workspaces',
        'subtitle' => 'Every workspace on the platform. Super-admin only — actions are audited.',
        'actions'  => component('button', [
            'label' => 'Users',
            'href'  => url('system/platform/users'),
            'variant' => 'secondary',
            'size'  => 'sm',
        ]),
    ]) ?>

    <?php $this->include('partials.alerts'); ?>

    <!-- Headline counters -->
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <?= component('stat', ['label' => 'Workspaces', 'value' => $stats['workspaces'] ?? 0]) ?>
        <?= component('stat', ['label' => 'Active / trial', 'value' => $stats['active_workspaces'] ?? 0]) ?>
        <?= component('stat', ['label' => 'Suspended', 'value' => $stats['suspended_workspaces'] ?? 0]) ?>
        <?= component('stat', ['label' => 'Active subscriptions', 'value' => $stats['active_subscriptions'] ?? 0]) ?>
    </div>

    <!-- Search -->
    <form method="GET" action="<?= e(url('system/platform/workspaces')) ?>" class="flex flex-wrap items-end gap-3">
        <div class="min-w-0 flex-1">
            <label class="label" for="q">Search workspaces</label>
            <input class="input" id="q" name="q" value="<?= e($keyword) ?>" placeholder="Name or slug…" autocomplete="off">
        </div>
        <?= component('button', ['label' => 'Search', 'type' => 'submit', 'variant' => 'secondary']) ?>
        <?php if ($keyword !== ''): ?>
            <?= component('button', ['label' => 'Clear', 'href' => url('system/platform/workspaces'), 'variant' => 'ghost']) ?>
        <?php endif; ?>
    </form>

    <?php if ($workspaces === []): ?>
        <?= component('card', ['slot' => component('state', [
            'variant' => 'empty',
            'title'   => $keyword !== '' ? 'No workspaces match your search' : 'No workspaces yet',
            'message' => $keyword !== ''
                ? 'Try a different name or slug.'
                : 'Workspaces created by tenants will appear here.',
        ])]) ?>
    <?php else: ?>
        <?php
        $rows = [];
        foreach ($workspaces as $w) {
            $statusBadge = component('badge', [
                'label'   => $dash($w['status_key']),
                'variant' => $statusVariant((string) $w['status_key']),
            ]);

            $planCell = e($dash($w['plan_name'] ?? null));
            if (! empty($w['subscription_status'])) {
                $planCell .= ' ' . component('badge', [
                    'label'   => (string) $w['subscription_status'],
                    'variant' => $statusVariant((string) $w['subscription_status']),
                    'class'   => 'ms-1',
                ]);
            }

            $owner = '<div class="leading-tight"><div class="font-medium text-slate-800 dark:text-slate-100">'
                . e($dash($w['owner_name'] ?? null))
                . '</div><div class="text-xs text-slate-500 dark:text-slate-400">'
                . e($dash($w['owner_email'] ?? null)) . '</div></div>';

            $name = '<div class="leading-tight"><div class="font-medium text-slate-900 dark:text-white">'
                . e((string) $w['name'])
                . '</div><div class="text-xs text-slate-500 dark:text-slate-400">'
                . e((string) $w['slug']) . '</div></div>';

            // Per-row actions: view + suspend/activate (each a CSRF POST form). The
            // suspend confirmation uses a modal; activate is a direct POST.
            $view = component('button', [
                'label'   => 'View',
                'href'    => url('system/platform/workspaces/show?id=' . (int) $w['id']),
                'variant' => 'ghost',
                'size'    => 'sm',
            ]);

            if (($w['status_key'] ?? '') === 'suspended') {
                $action = '<form method="POST" action="' . e(url('system/platform/workspaces/activate')) . '" class="inline">'
                    . csrf_field()
                    . '<input type="hidden" name="id" value="' . (int) $w['id'] . '">'
                    . component('button', ['label' => 'Activate', 'type' => 'submit', 'variant' => 'success', 'size' => 'sm'])
                    . '</form>';
            } else {
                $modalId = 'suspend-ws-' . (int) $w['id'];
                $trigger = component('button', [
                    'label'      => 'Suspend',
                    'variant'    => 'danger',
                    'size'       => 'sm',
                    'attributes' => ['data-modal-open' => $modalId],
                ]);
                $modal = component('modal', [
                    'id'     => $modalId,
                    'title'  => 'Suspend workspace?',
                    'slot'   => '<p>Suspending <strong>' . e((string) $w['name'])
                        . '</strong> blocks its members from working in it until you re-activate it. This is recorded in the audit log.</p>',
                    'footer' => '<form method="POST" action="' . e(url('system/platform/workspaces/suspend')) . '">'
                        . csrf_field()
                        . '<input type="hidden" name="id" value="' . (int) $w['id'] . '">'
                        . component('button', ['label' => 'Suspend workspace', 'type' => 'submit', 'variant' => 'danger'])
                        . '</form>',
                ]);
                $action = $trigger . $modal;
            }

            $actions = '<div class="flex items-center justify-end gap-1">' . $view . $action . '</div>';

            $rows[] = [
                $name,
                $owner,
                e((string) $w['member_count']),
                $statusBadge,
                $planCell,
                e($dash($w['created_at'] ?? null)),
                $actions,
            ];
        }
        ?>
        <?= component('table', [
            'columns' => [
                'Workspace',
                'Owner',
                ['label' => 'Members', 'align' => 'center'],
                'Status',
                'Plan',
                'Created',
                ['label' => 'Actions', 'align' => 'end'],
            ],
            'rows'  => $rows,
            'empty' => 'No workspaces to display.',
        ]) ?>
        <p class="text-xs text-slate-400 dark:text-slate-500">
            Showing the <?= e((string) count($workspaces)) ?> newest workspaces (capped at <?= e((string) $cap) ?>). Use search to narrow the list.
        </p>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
