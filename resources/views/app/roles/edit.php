<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<?php
/**
 * Role editor — name/priority plus the module-grouped permission matrix.
 * One card per system module, a checkbox per permission under it (checked = the
 * role has it). Used for both Create (role = null) and Edit.
 *
 * @var \App\Models\Role|null $role        the role being edited, or null on create
 * @var array<int,array{key:string,label:string,permissions:array<int,array{id:int,key:string,name:string,description:?string}>}> $modules
 * @var int[] $granted     permission ids the role currently holds
 * @var bool  $isOwner     the Owner role (`*` — every permission, read-only)
 * @var bool  $isSystem    a protected system role (name/priority locked)
 * @var int   $totalCount  total permissions in the catalogue (for the Owner view)
 */
$isEdit   = $role !== null;
// On create, fall back to old() so a failed submit repopulates the fields.
$roleName = $isEdit ? (string) $role->getAttribute('name') : (string) old('name', '');
$roleSlug = $isEdit ? (string) $role->getAttribute('slug') : '';
$priority = $isEdit ? (string) (int) $role->getAttribute('priority') : (string) old('priority', '');
$descr    = $isEdit ? (string) ($role->getAttribute('description') ?? '') : (string) old('description', '');

// Owner is read-only and implicitly holds everything; system roles lock the
// name/priority but still allow permission edits. After a failed submit, the
// ticked boxes come from old input so the selection survives the round-trip.
$oldPermissions = old('permissions', null);
$grantedIds = is_array($oldPermissions)
    ? array_map('intval', $oldPermissions)
    : array_map('intval', $granted);
$grantedSet  = array_fill_keys($grantedIds, true);
$lockMeta    = $isSystem;          // name/priority/slug locked for system roles
$lockMatrix  = $isOwner;           // the whole grant is config-owned for Owner
$formAction  = $isEdit ? url('roles/update') : url('roles');

$errors = session()->get('errors', []);
$nameError = ! empty($errors['name']) ? (is_array($errors['name']) ? (string) reset($errors['name']) : (string) $errors['name']) : null;

// Count permissions for the section summary.
$totalPerms = 0;
foreach ($modules as $module) {
    $totalPerms += count($module['permissions']);
}

$breadcrumb = '<a href="' . e(url('roles')) . '" class="text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">&larr; Back to roles</a>';
?>
<div class="space-y-6">
    <?= component('page-header', [
        'title'      => $isEdit ? 'Edit role' : 'Create role',
        'subtitle'   => $isOwner
            ? 'The Owner role always holds every permission and cannot be changed.'
            : 'Set the role name and choose its permissions, grouped by module.',
        'breadcrumb' => $breadcrumb,
    ]) ?>

    <?php if ($isOwner): ?>
        <?= component('alert', [
            'variant' => 'info',
            'title'   => 'Read-only role',
            'message' => 'Owner grants full control of the workspace. Its permissions are managed by the system and shown here for reference.',
        ]) ?>
    <?php elseif ($isSystem): ?>
        <?= component('alert', [
            'variant' => 'warning',
            'title'   => 'System role',
            'message' => 'This is a built-in role. Its name is fixed, but you can adjust which permissions it grants.',
        ]) ?>
    <?php endif; ?>

    <form method="post" action="<?= e($formAction) ?>" class="space-y-6">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= e((string) (int) $role->getKey()) ?>">
        <?php endif; ?>

        <?php
        // --- Role details card -------------------------------------------------
        $detailControls = component('field', [
            'label'    => 'Role name',
            'for'      => 'role-name',
            'name'     => 'name',
            'required' => true,
            'error'    => $nameError,
            'hint'     => $lockMeta ? 'System role names are fixed.' : 'A clear, human label (e.g. "Recruiter").',
            'control'  => component('input', [
                'name'       => 'name',
                'id'         => 'role-name',
                'value'      => $roleName,
                'placeholder' => 'e.g. Recruiter',
                'required'   => true,
                'disabled'   => $lockMeta,
                'error'      => $nameError !== null,
                'attributes' => ['maxlength' => '120'],
            ]),
        ]);

        $detailControls .= component('field', [
            'label'   => 'Priority',
            'for'     => 'role-priority',
            'name'    => 'priority',
            'class'   => 'mt-4',
            'hint'    => 'Higher numbers sort first when a member holds several roles (0–99).',
            'control' => component('input', [
                'name'       => 'priority',
                'id'         => 'role-priority',
                'type'       => 'number',
                'value'      => $priority,
                'placeholder' => '0',
                'disabled'   => $lockMeta,
                'attributes' => ['min' => '0', 'max' => '99', 'step' => '1'],
            ]),
        ]);

        $detailControls .= component('field', [
            'label'   => 'Description',
            'for'     => 'role-description',
            'name'    => 'description',
            'class'   => 'mt-4',
            'hint'    => 'Optional. What is this role for?',
            'control' => component('input', [
                'name'       => 'description',
                'id'         => 'role-description',
                'value'      => $descr,
                'placeholder' => 'e.g. Manages the hiring pipeline',
                'disabled'   => $lockMeta,
                'attributes' => ['maxlength' => '255'],
            ]),
        ]);

        if ($isEdit && $roleSlug !== '') {
            $detailControls .= '<div class="mt-4 text-xs text-slate-500">Slug: <span class="font-mono">' . e($roleSlug) . '</span></div>';
        }
        ?>
        <?= component('card', ['title' => 'Role details', 'slot' => $detailControls]) ?>

        <?php // --- Permission matrix ------------------------------------------- ?>
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Permissions</h2>
                <?php if ($isOwner): ?>
                    <?= component('badge', ['label' => 'All ' . (int) $totalCount . ' permissions granted', 'variant' => 'brand']) ?>
                <?php else: ?>
                    <span class="text-xs text-slate-500">Tick the permissions this role should grant.</span>
                <?php endif; ?>
            </div>

            <?php if ($modules === []): ?>
                <?= component('state', [
                    'variant' => 'empty',
                    'title'   => 'No permissions available',
                    'message' => 'The permission catalogue is empty. Permissions appear here once modules are registered.',
                ]) ?>
            <?php else: ?>
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    <?php foreach ($modules as $module): ?>
                        <?php
                        $checks = '<div class="space-y-3">';
                        foreach ($module['permissions'] as $perm) {
                            $pid       = (int) $perm['id'];
                            $isChecked = $lockMatrix || isset($grantedSet[$pid]);
                            $inputId   = 'perm-' . $pid;
                            $checks .= '<div class="flex items-start gap-3">'
                                . '<input type="checkbox" name="permissions[]" value="' . e((string) $pid) . '"'
                                . ' id="' . e($inputId) . '"'
                                . ' class="mt-0.5 h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600 dark:bg-slate-800"'
                                . ($isChecked ? ' checked' : '')
                                . ($lockMatrix ? ' disabled' : '')
                                . '>'
                                . '<label for="' . e($inputId) . '" class="min-w-0 cursor-pointer select-none">'
                                . '<span class="block text-sm font-medium text-slate-800 dark:text-slate-100">' . e((string) $perm['name']) . '</span>'
                                . '<span class="block font-mono text-xs text-slate-400">' . e((string) $perm['key']) . '</span>';
                            if (! empty($perm['description'])) {
                                $checks .= '<span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">' . e((string) $perm['description']) . '</span>';
                            }
                            $checks .= '</label></div>';
                        }
                        $checks .= '</div>';

                        $moduleHeader = '<div class="flex items-center justify-between">'
                            . '<h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">' . e((string) $module['label']) . '</h3>'
                            . component('badge', ['label' => (string) count($module['permissions']), 'variant' => 'slate'])
                            . '</div>';
                        ?>
                        <?= component('card', ['header' => $moduleHeader, 'slot' => $checks]) ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <?= component('button', ['label' => 'Cancel', 'variant' => 'ghost', 'href' => url('roles')]) ?>
            <?php if (! $isOwner): ?>
                <?= component('button', ['label' => $isEdit ? 'Save changes' : 'Create role', 'type' => 'submit']) ?>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php $this->endSection(); ?>
