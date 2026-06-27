<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<?php
/**
 * Files — the documents uploaded into this workspace (docs/30 File Upload).
 *
 * @var array<int,array<string,mixed>> $files     listing rows (with 'uploader', 'size_human')
 * @var bool                           $canManage  may upload/delete (recruitment.manage)
 */
$mimeBadge = static function (string $mime): string {
    $short = match (true) {
        str_contains($mime, 'pdf')                 => 'PDF',
        str_contains($mime, 'word'),
        $mime === 'application/msword'             => 'DOC',
        str_contains($mime, 'spreadsheet'),
        str_contains($mime, 'excel'),
        str_contains($mime, 'csv')                 => 'SHEET',
        str_starts_with($mime, 'image/')           => strtoupper(substr($mime, 6)),
        str_contains($mime, 'zip')                 => 'ZIP',
        str_starts_with($mime, 'text/')            => 'TEXT',
        default                                    => 'FILE',
    };
    $variant = str_starts_with($mime, 'image/') ? 'info' : 'slate';

    return component('badge', ['label' => $short, 'variant' => $variant]);
};

// Build each file row as raw-HTML cells for the table component.
$rows = [];
foreach ($files as $f) {
    $id = (int) $f['id'];
    $name = (string) ($f['original_name'] ?? '');

    $nameCell = '<a href="' . e(url('files/download?id=' . $id)) . '" class="font-medium text-brand-600 hover:underline dark:text-brand-400">'
        . e($name) . '</a>';

    $actions = '<div class="flex items-center justify-end gap-2">';
    $actions .= component('button', [
        'label'   => 'Download',
        'variant' => 'ghost',
        'size'    => 'sm',
        'href'    => url('files/download?id=' . $id),
    ]);
    if ($canManage) {
        $actions .= '<form method="post" action="' . e(url('files/delete')) . '">'
            . csrf_field()
            . '<input type="hidden" name="id" value="' . e((string) $id) . '">'
            . component('button', ['label' => 'Delete', 'variant' => 'danger', 'size' => 'sm', 'type' => 'submit', 'confirm' => 'Delete this file?'])
            . '</form>';
    }
    $actions .= '</div>';

    $rows[] = [
        $nameCell,
        $mimeBadge((string) ($f['mime'] ?? '')),
        '<span class="text-sm text-slate-500">' . e((string) ($f['size_human'] ?? '')) . '</span>',
        e((string) ($f['uploader'] ?? '—')),
        '<span class="text-sm text-slate-500">' . e((string) ($f['created_at'] ?? '')) . '</span>',
        $actions,
    ];
}

// Upload form (a single multipart file). Hidden entirely without manage rights.
$uploadForm = '<form method="POST" enctype="multipart/form-data" action="' . e(url('files/upload')) . '" class="flex flex-wrap items-center gap-3">'
    . csrf_field()
    . '<input type="file" name="file" required class="block text-sm text-slate-600 file:me-3 file:rounded-lg file:border-0 file:bg-brand-600 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-brand-700 dark:text-slate-300">'
    . component('button', ['label' => 'Upload', 'type' => 'submit'])
    . '</form>';
?>
<div class="space-y-6">
    <?= component('page-header', [
        'title'    => 'Files',
        'subtitle' => 'Documents uploaded into this workspace.',
    ]) ?>

    <?php if ($canManage): ?>
        <?= component('card', [
            'title' => 'Upload a file',
            'slot'  => $uploadForm,
        ]) ?>
    <?php endif; ?>

    <?php if ($rows === []): ?>
        <?= component('state', [
            'variant' => 'empty',
            'title'   => 'No files yet',
            'message' => $canManage
                ? 'Upload a document to get started.'
                : 'No files have been uploaded to this workspace yet.',
        ]) ?>
    <?php else: ?>
        <?= component('table', [
            'columns' => [
                'Name',
                'Type',
                'Size',
                'Uploaded by',
                'Uploaded',
                ['label' => 'Actions', 'align' => 'end'],
            ],
            'rows'  => $rows,
            'empty' => 'No files yet.',
        ]) ?>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
