<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<?php
/**
 * Members & Roles — the people in the workspace with their tenant roles.
 *
 * @var array<int,array<string,mixed>> $members   memberships+users (with 'roles', 'status')
 * @var array<int,array<string,mixed>> $roles     assignable workspace roles [id,name,slug]
 * @var bool $canInvite @var bool $canUpdate @var bool $canRemove
 * @var \App\Models\Membership|null $activeMember
 * @var array<int,array<string,mixed>> $activity
 */
$statusVariant = static fn (string $s): string => match ($s) {
    'active'    => 'green',
    'suspended' => 'red',
    'invited'   => 'amber',
    default     => 'slate',
};

// Role options for the invite <select> (id => name).
$roleOptions = [];
foreach ($roles as $r) {
    $roleOptions[(int) $r['id']] = (string) $r['name'];
}

// Build each member row as raw-HTML cells for the table component.
$rows = [];
foreach ($members as $m) {
    $status = (string) ($m['status'] ?? 'active');
    $memberRoleIds = array_map(static fn (array $role): int => (int) $role['id'], $m['roles']);

    // Role badges.
    $roleBadges = '';
    foreach ($m['roles'] as $role) {
        $roleBadges .= component('badge', ['label' => (string) $role['name'], 'variant' => 'brand', 'class' => 'me-1 mb-1']);
    }
    if ($roleBadges === '') {
        $roleBadges = '<span class="text-xs text-slate-400">No roles</span>';
    }

    // Per-row actions: edit roles (modal), activity, deactivate/reactivate.
    $actions = '<div class="flex items-center justify-end gap-2">';
    $actions .= component('button', [
        'label'      => 'Activity',
        'variant'    => 'ghost',
        'size'       => 'sm',
        'href'       => url('members?activity=' . (int) $m['id']),
    ]);
    if ($canUpdate && $roleOptions !== []) {
        $actions .= component('button', [
            'label'      => 'Edit roles',
            'variant'    => 'secondary',
            'size'       => 'sm',
            'attributes' => ['data-modal-open' => 'roles-' . (int) $m['id']],
        ]);
    }
    if ($canRemove) {
        if ($status === 'suspended') {
            $actions .= '<form method="post" action="' . e(url('members/reactivate')) . '">'
                . csrf_field()
                . '<input type="hidden" name="membership_id" value="' . e((string) $m['id']) . '">'
                . component('button', ['label' => 'Reactivate', 'variant' => 'success', 'size' => 'sm', 'type' => 'submit'])
                . '</form>';
        } else {
            $actions .= '<form method="post" action="' . e(url('members/deactivate')) . '">'
                . csrf_field()
                . '<input type="hidden" name="membership_id" value="' . e((string) $m['id']) . '">'
                . component('button', ['label' => 'Deactivate', 'variant' => 'danger', 'size' => 'sm', 'type' => 'submit', 'confirm' => 'Deactivate this member?'])
                . '</form>';
        }
    }
    $actions .= '</div>';

    $nameCell = '<div class="font-medium text-slate-900 dark:text-white">' . e((string) ($m['name'] ?? '')) . '</div>'
        . '<div class="text-xs text-slate-500">' . e((string) ($m['email'] ?? '')) . '</div>';

    $rows[] = [
        $nameCell,
        e((string) ($m['title'] ?? '—')),
        component('badge', ['label' => $status, 'variant' => $statusVariant($status), 'dot' => true]),
        $roleBadges,
        '<span class="text-sm text-slate-500">' . e((string) ($m['joined_at'] ?? '')) . '</span>',
        $actions,
    ];
}

// Invite modal body (a form posting to members/invite).
$inviteForm = '<form method="post" action="' . e(url('members/invite')) . '" class="space-y-4">'
    . csrf_field()
    . component('field', [
        'label'   => 'Full name', 'for' => 'invite-name', 'name' => 'name', 'required' => true,
        'control' => component('input', ['name' => 'name', 'id' => 'invite-name', 'placeholder' => 'e.g. Sara Ahmed', 'required' => true]),
    ])
    . component('field', [
        'label'   => 'Email', 'for' => 'invite-email', 'name' => 'email', 'required' => true,
        'control' => component('input', ['name' => 'email', 'id' => 'invite-email', 'type' => 'email', 'placeholder' => 'name@company.com', 'required' => true]),
    ])
    . component('field', [
        'label'   => 'Title', 'for' => 'invite-title', 'name' => 'title',
        'hint'    => 'Optional job title in this workspace.',
        'control' => component('input', ['name' => 'title', 'id' => 'invite-title', 'placeholder' => 'e.g. Recruiter']),
    ])
    . component('field', [
        'label'   => 'Initial role', 'for' => 'invite-role', 'name' => 'role_id',
        'control' => component('select', ['name' => 'role_id', 'id' => 'invite-role', 'options' => $roleOptions, 'placeholder' => 'Select a role']),
    ])
    . '<div class="flex items-center justify-end gap-2 pt-2">'
    . component('button', ['label' => 'Cancel', 'variant' => 'ghost', 'attributes' => ['data-modal-close' => true]])
    . component('button', ['label' => 'Send invite', 'type' => 'submit'])
    . '</div>'
    . '</form>';

$headerActions = $canInvite
    ? component('button', ['label' => 'Invite member', 'attributes' => ['data-modal-open' => 'invite-member']])
    : '';
?>
<div class="space-y-6">
    <?= component('page-header', [
        'title'    => 'Members',
        'subtitle' => 'People in this workspace and the roles they hold.',
        'actions'  => $headerActions,
    ]) ?>

    <?= component('table', [
        'columns' => [
            'Member',
            'Title',
            'Status',
            'Roles',
            'Joined',
            ['label' => 'Actions', 'align' => 'end'],
        ],
        'rows'  => $rows,
        'empty' => 'No members yet.',
    ]) ?>

    <?php if ($activeMember !== null): ?>
        <?php
        $activityBody = '';
        if ($activity === []) {
            $activityBody = '<p class="text-sm text-slate-500">No recent activity.</p>';
        } else {
            $activityBody = '<ul class="divide-y divide-slate-100 dark:divide-slate-800">';
            foreach ($activity as $log) {
                $activityBody .= '<li class="flex items-center justify-between gap-4 py-2 text-sm">'
                    . '<span class="text-slate-700 dark:text-slate-200">' . e((string) ($log['description'] ?? $log['action'] ?? '')) . '</span>'
                    . '<span class="shrink-0 text-xs text-slate-400">' . e((string) ($log['created_at'] ?? '')) . '</span>'
                    . '</li>';
            }
            $activityBody .= '</ul>';
        }
        ?>
        <?= component('card', [
            'title'   => 'Recent activity',
            'actions' => component('button', ['label' => 'Close', 'variant' => 'ghost', 'size' => 'sm', 'href' => url('members')]),
            'slot'    => $activityBody,
        ]) ?>
    <?php endif; ?>
</div>

<?php if ($canInvite): ?>
    <?= component('modal', [
        'id'    => 'invite-member',
        'title' => 'Invite a member',
        'slot'  => $inviteForm,
    ]) ?>
<?php endif; ?>

<?php if ($canUpdate && $roleOptions !== []): ?>
    <?php foreach ($members as $m): ?>
        <?php
        $memberRoleIds = array_map(static fn (array $role): int => (int) $role['id'], $m['roles']);
        $checkboxes = '';
        foreach ($roles as $r) {
            $rid = (int) $r['id'];
            $checked = in_array($rid, $memberRoleIds, true) ? ' checked' : '';
            $checkboxes .= '<label class="flex items-center gap-2 py-1 text-sm text-slate-700 dark:text-slate-200">'
                . '<input type="checkbox" name="roles[]" value="' . e((string) $rid) . '" class="rounded border-slate-300"' . $checked . '>'
                . e((string) $r['name'])
                . '</label>';
        }
        $rolesForm = '<form method="post" action="' . e(url('members/update-roles')) . '" class="space-y-4">'
            . csrf_field()
            . '<input type="hidden" name="membership_id" value="' . e((string) $m['id']) . '">'
            . '<div class="space-y-1">' . $checkboxes . '</div>'
            . '<div class="flex items-center justify-end gap-2 pt-2">'
            . component('button', ['label' => 'Cancel', 'variant' => 'ghost', 'attributes' => ['data-modal-close' => true]])
            . component('button', ['label' => 'Save roles', 'type' => 'submit'])
            . '</div>'
            . '</form>';
        ?>
        <?= component('modal', [
            'id'    => 'roles-' . (int) $m['id'],
            'title' => 'Roles · ' . (string) ($m['name'] ?? ''),
            'slot'  => $rolesForm,
        ]) ?>
    <?php endforeach; ?>
<?php endif; ?>
<?php $this->endSection(); ?>
