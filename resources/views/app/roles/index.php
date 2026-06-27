<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<?php
/**
 * Roles & Permissions — the tenant roles in this workspace and what they grant.
 *
 * @var array<int,array<string,mixed>> $roles      [id,name,slug,description,priority,is_system,member_count,permission_count]
 * @var bool $canManage  whether the viewer may create/edit/delete roles
 */
$rows = [];
foreach ($roles as $role) {
    $id       = (int) $role['id'];
    $isSystem = (bool) $role['is_system'];
    $isOwner  = $isSystem && ($role['slug'] ?? '') === 'owner';

    // Name + slug, with a "System" badge for protected roles.
    $nameCell = '<div class="flex items-center gap-2">'
        . '<span class="font-medium text-slate-900 dark:text-white">' . e((string) $role['name']) . '</span>';
    if ($isSystem) {
        $nameCell .= component('badge', ['label' => 'System', 'variant' => 'slate']);
    }
    $nameCell .= '</div>'
        . '<div class="mt-0.5 text-xs text-slate-500">'
        . '<span class="font-mono">' . e((string) $role['slug']) . '</span>';
    if (! empty($role['description'])) {
        $nameCell .= ' · ' . e((string) $role['description']);
    }
    $nameCell .= '</div>';

    // Permission summary: Owner shows "All permissions"; others a count.
    if ($isOwner) {
        $permCell = component('badge', ['label' => 'All permissions', 'variant' => 'brand']);
    } else {
        $count = (int) $role['permission_count'];
        $permCell = '<span class="text-sm text-slate-700 dark:text-slate-200">'
            . e((string) $count . ' ' . ($count === 1 ? 'permission' : 'permissions'))
            . '</span>';
    }

    $memberCell = '<span class="text-sm text-slate-700 dark:text-slate-200">'
        . e((string) (int) $role['member_count'])
        . '</span>';

    // Actions: edit (manage), delete (manage + non-system only).
    $actions = '<div class="flex items-center justify-end gap-2">';
    if ($canManage) {
        $editLabel = $isOwner ? 'View' : 'Edit';
        $actions .= component('button', [
            'label'   => $editLabel,
            'variant' => 'secondary',
            'size'    => 'sm',
            'href'    => url('roles/edit?id=' . $id),
        ]);
        if (! $isSystem) {
            $actions .= '<form method="post" action="' . e(url('roles/delete')) . '" class="inline">'
                . csrf_field()
                . '<input type="hidden" name="id" value="' . e((string) $id) . '">'
                . component('button', [
                    'label'   => 'Delete',
                    'variant' => 'danger',
                    'size'    => 'sm',
                    'type'    => 'submit',
                    'confirm' => 'Delete the role "' . (string) $role['name'] . '"? Members lose it immediately.',
                ])
                . '</form>';
        }
    } else {
        $actions .= '<span class="text-xs text-slate-400">—</span>';
    }
    $actions .= '</div>';

    $rows[] = [$nameCell, $memberCell, $permCell, $actions];
}

$headerActions = $canManage
    ? component('button', ['label' => 'Create role', 'href' => url('roles/create')])
    : '';
?>
<div class="space-y-6">
    <?= component('page-header', [
        'title'    => 'Roles & Permissions',
        'subtitle' => 'Roles control what people in this workspace can do. Permissions are grouped by module.',
        'actions'  => $headerActions,
    ]) ?>

    <?php if ($roles === []): ?>
        <?= component('state', [
            'variant' => 'empty',
            'title'   => 'No roles yet',
            'message' => 'Create a role to start granting permissions to members.',
            'action'  => $canManage
                ? component('button', ['label' => 'Create role', 'href' => url('roles/create')])
                : '',
        ]) ?>
    <?php else: ?>
        <?= component('table', [
            'columns' => [
                'Role',
                ['label' => 'Members', 'align' => 'start'],
                'Permissions',
                ['label' => 'Actions', 'align' => 'end'],
            ],
            'rows'  => $rows,
            'empty' => 'No roles yet.',
        ]) ?>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
