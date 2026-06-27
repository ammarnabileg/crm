<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<?php
/**
 * Super-Admin Platform Console — every user on the platform.
 *
 * @var string                           $title
 * @var array<int, array<string, mixed>> $users
 * @var array<string, int>               $stats
 * @var string                           $keyword
 * @var int                              $cap
 * @var ?int                             $currentId   The acting super admin's id.
 * @var bool                             $impersonating
 */
$statusVariant = static function (string $key): string {
    return match ($key) {
        'active'              => 'green',
        'pending', 'invited'  => 'info',
        'suspended', 'banned' => 'red',
        'inactive'            => 'amber',
        default               => 'slate',
    };
};
$dash = static fn (?string $v): string => ($v !== null && $v !== '') ? $v : '—';
?>
<div class="space-y-6">
    <?= component('page-header', [
        'title'    => 'Platform · Users',
        'subtitle' => 'Every user across all workspaces. Super-admin only — actions are audited.',
        'actions'  => component('button', [
            'label'   => 'Workspaces',
            'href'    => url('system/platform/workspaces'),
            'variant' => 'secondary',
            'size'    => 'sm',
        ]),
    ]) ?>

    <?php $this->include('partials.alerts'); ?>

    <?php if ($impersonating): ?>
        <div class="alert-warning flex flex-wrap items-center justify-between gap-3">
            <span>You are currently impersonating another user.</span>
            <form method="POST" action="<?= e(url('system/platform/stop-impersonating')) ?>" class="inline">
                <?= csrf_field() ?>
                <?= component('button', ['label' => 'Stop impersonating', 'type' => 'submit', 'variant' => 'warning', 'size' => 'sm']) ?>
            </form>
        </div>
    <?php endif; ?>

    <!-- Headline counters -->
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <?= component('stat', ['label' => 'Users', 'value' => $stats['users'] ?? 0]) ?>
        <?= component('stat', ['label' => 'Active', 'value' => $stats['active_users'] ?? 0]) ?>
        <?= component('stat', ['label' => 'Workspaces', 'value' => $stats['workspaces'] ?? 0]) ?>
        <?= component('stat', ['label' => 'Active subscriptions', 'value' => $stats['active_subscriptions'] ?? 0]) ?>
    </div>

    <!-- Search -->
    <form method="GET" action="<?= e(url('system/platform/users')) ?>" class="flex flex-wrap items-end gap-3">
        <div class="min-w-0 flex-1">
            <label class="label" for="q">Search users</label>
            <input class="input" id="q" name="q" value="<?= e($keyword) ?>" placeholder="Name or email…" autocomplete="off">
        </div>
        <?= component('button', ['label' => 'Search', 'type' => 'submit', 'variant' => 'secondary']) ?>
        <?php if ($keyword !== ''): ?>
            <?= component('button', ['label' => 'Clear', 'href' => url('system/platform/users'), 'variant' => 'ghost']) ?>
        <?php endif; ?>
    </form>

    <?php if ($users === []): ?>
        <?= component('card', ['slot' => component('state', [
            'variant' => 'empty',
            'title'   => $keyword !== '' ? 'No users match your search' : 'No users yet',
            'message' => $keyword !== '' ? 'Try a different name or email.' : 'Users will appear here as they register.',
        ])]) ?>
    <?php else: ?>
        <?php
        $rows = [];
        foreach ($users as $u) {
            $id = (int) $u['id'];
            $isSelf = $currentId !== null && $id === (int) $currentId;

            $identity = '<div class="leading-tight"><div class="font-medium text-slate-900 dark:text-white">'
                . e((string) $u['name'])
                . ($isSelf ? ' ' . component('badge', ['label' => 'You', 'variant' => 'brand']) : '')
                . '</div><div class="text-xs text-slate-500 dark:text-slate-400">'
                . e((string) $u['email']) . '</div></div>';

            $statusBadge = component('badge', [
                'label'   => $dash($u['status_key']),
                'variant' => $statusVariant((string) $u['status_key']),
            ]);

            // Impersonate (only other, active users).
            $impersonate = '';
            if (! $isSelf && ($u['status_key'] ?? '') === 'active' && ! $impersonating) {
                $impersonate = '<form method="POST" action="' . e(url('system/platform/impersonate')) . '" class="inline">'
                    . csrf_field()
                    . '<input type="hidden" name="user_id" value="' . $id . '">'
                    . component('button', [
                        'label'   => 'Impersonate',
                        'type'    => 'submit',
                        'variant' => 'ghost',
                        'size'    => 'sm',
                        'confirm' => 'Start acting as this user? This is recorded in the audit log.',
                    ])
                    . '</form>';
            }

            // Suspend / activate (never your own account: suspend is hidden for self).
            if (($u['status_key'] ?? '') === 'suspended') {
                $statusAction = '<form method="POST" action="' . e(url('system/platform/users/activate')) . '" class="inline">'
                    . csrf_field()
                    . '<input type="hidden" name="id" value="' . $id . '">'
                    . component('button', ['label' => 'Activate', 'type' => 'submit', 'variant' => 'success', 'size' => 'sm'])
                    . '</form>';
            } elseif ($isSelf) {
                $statusAction = '';
            } else {
                $modalId = 'suspend-user-' . $id;
                $trigger = component('button', [
                    'label'      => 'Suspend',
                    'variant'    => 'danger',
                    'size'       => 'sm',
                    'attributes' => ['data-modal-open' => $modalId],
                ]);
                $modal = component('modal', [
                    'id'     => $modalId,
                    'title'  => 'Suspend user?',
                    'slot'   => '<p>Suspending <strong>' . e((string) $u['name'])
                        . '</strong> immediately blocks them from signing in and ends their live session. This is recorded in the audit log.</p>',
                    'footer' => '<form method="POST" action="' . e(url('system/platform/users/suspend')) . '">'
                        . csrf_field()
                        . '<input type="hidden" name="id" value="' . $id . '">'
                        . component('button', ['label' => 'Suspend user', 'type' => 'submit', 'variant' => 'danger'])
                        . '</form>',
                ]);
                $statusAction = $trigger . $modal;
            }

            $actions = '<div class="flex items-center justify-end gap-1">' . $impersonate . $statusAction . '</div>';

            $rows[] = [
                $identity,
                $statusBadge,
                e((string) $u['workspaces_count']),
                e($dash($u['last_login_at'] ?? null)),
                e($dash($u['created_at'] ?? null)),
                $actions,
            ];
        }
        ?>
        <?= component('table', [
            'columns' => [
                'User',
                'Status',
                ['label' => 'Workspaces', 'align' => 'center'],
                'Last login',
                'Created',
                ['label' => 'Actions', 'align' => 'end'],
            ],
            'rows'  => $rows,
            'empty' => 'No users to display.',
        ]) ?>
        <p class="text-xs text-slate-400 dark:text-slate-500">
            Showing the <?= e((string) count($users)) ?> newest users (capped at <?= e((string) $cap) ?>). Use search to narrow the list.
        </p>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
