<?php
/**
 * Design System catalog (docs/30) — living documentation. Every Design System
 * component is rendered here with its variants and states, in the real app shell,
 * so light/dark, RTL/LTR and accessibility can be reviewed in one place. This page
 * is itself built only from the component() library — no bespoke one-off UI.
 */
$this->extends('layouts.app');
$this->section('content');

/** Small demo helper: a labelled cell wrapping one or more rendered components. */
$demo = static function (string $label, string $html): string {
    return '<div class="space-y-2">'
        . '<p class="text-xs font-medium uppercase tracking-wide text-slate-400">' . e($label) . '</p>'
        . '<div class="flex flex-wrap items-center gap-3">' . $html . '</div></div>';
};

/** Design-token colour values (from tailwind.config.js) shown as swatches. */
$tokens = [
    'primary' => '#2457eb', 'success' => '#16a34a', 'warning' => '#f59e0b',
    'danger' => '#dc2626', 'info' => '#0284c7', 'slate' => '#475569',
];
$brandScale = [
    '50' => '#eef5ff', '100' => '#d9e8ff', '200' => '#bcd7ff', '300' => '#8ebdff', '400' => '#5897ff',
    '500' => '#3b76f6', '600' => '#2457eb', '700' => '#1c43d8', '800' => '#1d39af', '900' => '#1d348a',
];

$sections = [
    'foundations' => 'Foundations', 'buttons' => 'Buttons', 'forms' => 'Forms',
    'data' => 'Data display', 'feedback' => 'Feedback', 'overlays' => 'Overlays',
    'search-alerts' => 'Search & alerts', 'states' => 'States',
];
?>

<?= component('page-header', [
    'breadcrumb' => component('breadcrumb', ['items' => [
        ['label' => 'Dashboard', 'href' => url('dashboard')],
        ['label' => 'Design System'],
    ]]),
    'title' => 'Design System',
    'subtitle' => 'One source of UI truth — tokens, components and states, light & dark, RTL/LTR.',
    'actions' => component('button', [
        'label' => 'Show toast',
        'attributes' => ['data-toast-demo' => true, 'data-toast-variant' => 'success', 'data-toast-message' => 'This is a toast notification.'],
    ]),
]) ?>

<nav class="mb-6 flex flex-wrap gap-2">
    <?php foreach ($sections as $id => $label): ?>
        <a href="#<?= e($id) ?>" class="chip hover:bg-slate-200 dark:hover:bg-slate-700"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>

<!-- ============================ FOUNDATIONS ============================ -->
<section id="foundations" class="mb-10 scroll-mt-6">
    <h2 class="mb-3 text-lg font-bold text-slate-900 dark:text-white">Foundations</h2>
    <div class="card card-body space-y-8">
        <div class="space-y-3">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Semantic colour tokens</p>
            <div class="flex flex-wrap gap-4">
                <?php foreach ($tokens as $name => $hex): ?>
                    <div class="space-y-1.5 text-center">
                        <div class="h-14 w-20 rounded-lg ring-1 ring-black/5" style="background-color: <?= e($hex) ?>"></div>
                        <p class="text-xs font-medium text-slate-600 dark:text-slate-300"><?= e($name) ?></p>
                        <p class="text-[10px] text-slate-400"><?= e($hex) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="space-y-3">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Primary scale</p>
            <div class="flex flex-wrap gap-1.5">
                <?php foreach ($brandScale as $shade => $hex): ?>
                    <div class="space-y-1 text-center">
                        <div class="h-10 w-14 rounded-md ring-1 ring-black/5" style="background-color: <?= e($hex) ?>"></div>
                        <p class="text-[10px] text-slate-400"><?= e($shade) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="grid gap-6 sm:grid-cols-2">
            <div class="space-y-2">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Typography</p>
                <p class="text-3xl font-bold text-slate-900 dark:text-white">Display 3xl</p>
                <p class="text-xl font-semibold text-slate-800 dark:text-slate-100">Heading xl</p>
                <p class="text-base text-slate-700 dark:text-slate-200">Body base — the quick brown fox.</p>
                <p class="text-sm text-slate-500 dark:text-slate-400">Small / muted text.</p>
            </div>
            <div class="space-y-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Radius &amp; elevation</p>
                <div class="flex items-center gap-3">
                    <div class="h-12 w-12 rounded-md bg-white shadow-sm ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700"></div>
                    <div class="h-12 w-12 rounded-lg bg-white shadow-md ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700"></div>
                    <div class="h-12 w-12 rounded-xl bg-white shadow-lg ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700"></div>
                    <div class="h-12 w-12 rounded-full bg-white shadow-xl ring-1 ring-slate-200 dark:bg-slate-800 dark:ring-slate-700"></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================== BUTTONS ============================== -->
<section id="buttons" class="mb-10 scroll-mt-6">
    <h2 class="mb-3 text-lg font-bold text-slate-900 dark:text-white">Buttons</h2>
    <div class="card card-body space-y-6">
        <?= $demo('Variants',
            component('button', ['label' => 'Primary'])
            . component('button', ['label' => 'Secondary', 'variant' => 'secondary'])
            . component('button', ['label' => 'Success', 'variant' => 'success'])
            . component('button', ['label' => 'Warning', 'variant' => 'warning'])
            . component('button', ['label' => 'Danger', 'variant' => 'danger'])
            . component('button', ['label' => 'Ghost', 'variant' => 'ghost'])
        ) ?>
        <?= $demo('Sizes',
            component('button', ['label' => 'Small', 'size' => 'sm'])
            . component('button', ['label' => 'Medium'])
            . component('button', ['label' => 'Large', 'size' => 'lg'])
        ) ?>
        <?= $demo('States &amp; link',
            component('button', ['label' => 'Disabled', 'disabled' => true])
            . component('button', ['label' => 'As link', 'href' => '#buttons', 'variant' => 'secondary'])
            . component('button', ['label' => 'Delete', 'variant' => 'danger', 'confirm' => 'Delete this item?'])
        ) ?>
    </div>
</section>

<!-- =============================== FORMS =============================== -->
<section id="forms" class="mb-10 scroll-mt-6">
    <h2 class="mb-3 text-lg font-bold text-slate-900 dark:text-white">Forms</h2>
    <div class="card card-body grid gap-6 sm:grid-cols-2">
        <?= component('field', [
            'label' => 'Email', 'for' => 'ds-email', 'hint' => 'We never share it.',
            'control' => component('input', ['name' => 'ds-email', 'id' => 'ds-email', 'type' => 'email', 'placeholder' => 'you@company.com']),
        ]) ?>
        <?= component('field', [
            'label' => 'Job title', 'for' => 'ds-title', 'required' => true, 'error' => 'This field is required.',
            'control' => component('input', ['name' => 'ds-title', 'id' => 'ds-title', 'error' => true, 'placeholder' => 'e.g. Senior Engineer']),
        ]) ?>
        <?= component('field', [
            'label' => 'Description', 'for' => 'ds-desc',
            'control' => component('textarea', ['name' => 'ds-desc', 'id' => 'ds-desc', 'rows' => 3, 'placeholder' => 'A short description…']),
        ]) ?>
        <?= component('field', [
            'label' => 'Status', 'for' => 'ds-status',
            'control' => component('select', ['name' => 'ds-status', 'id' => 'ds-status', 'placeholder' => 'Choose…', 'options' => ['open' => 'Open', 'paused' => 'Paused', 'closed' => 'Closed']]),
        ]) ?>
        <div class="space-y-3">
            <?= component('checkbox', ['name' => 'ds-remember', 'label' => 'Remember me', 'checked' => true]) ?>
            <div class="flex gap-4">
                <?= component('radio', ['name' => 'ds-plan', 'value' => 'basic', 'label' => 'Basic', 'checked' => true]) ?>
                <?= component('radio', ['name' => 'ds-plan', 'value' => 'pro', 'label' => 'Pro']) ?>
            </div>
        </div>
        <div class="space-y-3">
            <?= component('switch', ['name' => 'ds-notify', 'label' => 'Email notifications', 'on' => true]) ?>
            <?= component('switch', ['name' => 'ds-beta', 'label' => 'Join beta']) ?>
        </div>
        <?= component('field', [
            'label' => 'Start date', 'for' => 'ds-date',
            'control' => component('datepicker', ['name' => 'ds-date', 'id' => 'ds-date']),
        ]) ?>
        <?= component('field', [
            'label' => 'Skill (autocomplete)', 'for' => 'ds-skill', 'hint' => 'Type to filter',
            'control' => component('autocomplete', ['name' => 'ds-skill', 'id' => 'ds-skill', 'placeholder' => 'e.g. PHP', 'options' => ['PHP', 'MySQL', 'Tailwind CSS', 'JavaScript', 'Docker', 'Kubernetes']]),
        ]) ?>
        <div class="sm:col-span-2">
            <?= component('field', [
                'label' => 'Résumé', 'for' => 'ds-cv',
                'control' => component('file-upload', ['name' => 'ds-cv', 'id' => 'ds-cv', 'accept' => '.pdf', 'hint' => 'PDF up to 5MB']),
            ]) ?>
        </div>
    </div>
</section>

<!-- ============================ DATA DISPLAY =========================== -->
<section id="data" class="mb-10 scroll-mt-6">
    <h2 class="mb-3 text-lg font-bold text-slate-900 dark:text-white">Data display</h2>
    <div class="card card-body space-y-6">
        <?= $demo('Avatars',
            component('avatar', ['name' => 'Sara Ali', 'size' => 'sm'])
            . component('avatar', ['name' => 'Omar Nabil'])
            . component('avatar', ['name' => 'HalaOps Team', 'size' => 'lg'])
        ) ?>
        <?= $demo('Badges',
            component('badge', ['label' => 'Open', 'variant' => 'green', 'dot' => true])
            . component('badge', ['label' => 'Pending', 'variant' => 'amber'])
            . component('badge', ['label' => 'Rejected', 'variant' => 'red'])
            . component('badge', ['label' => 'Draft', 'variant' => 'slate'])
            . component('badge', ['label' => 'Interview', 'variant' => 'brand'])
            . component('badge', ['label' => 'Info', 'variant' => 'info'])
        ) ?>
        <?= $demo('Chips',
            component('chip', ['label' => 'PHP'])
            . component('chip', ['label' => 'MySQL', 'removable' => true, 'value' => 'mysql'])
            . component('chip', ['label' => 'Remote', 'removable' => true, 'value' => 'remote'])
        ) ?>
        <div class="space-y-2">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Stats</p>
            <div class="grid gap-4 sm:grid-cols-3">
                <?= component('stat', ['label' => 'Open jobs', 'value' => 12, 'delta' => '+3 this week', 'trend' => 'up']) ?>
                <?= component('stat', ['label' => 'Applications', 'value' => 348, 'delta' => '+18%', 'trend' => 'up']) ?>
                <?= component('stat', ['label' => 'Time to hire', 'value' => '21d', 'delta' => '-2d', 'trend' => 'down']) ?>
            </div>
        </div>
        <div class="space-y-2">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Card</p>
            <?= component('card', [
                'title'  => 'Candidate summary',
                'actions' => component('badge', ['label' => 'Shortlisted', 'variant' => 'green']),
                'slot'   => '<p class="text-sm text-slate-600 dark:text-slate-300">A surface container with an optional header, body and footer — the standard wrapper for grouped content.</p>',
                'footer' => component('button', ['label' => 'View profile', 'size' => 'sm']),
            ]) ?>
        </div>
        <div class="space-y-2">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Table</p>
            <?= component('table', [
                'columns' => ['Candidate', 'Stage', ['label' => 'Score', 'align' => 'end']],
                'rows' => [
                    [e('Sara Ali'), component('badge', ['label' => 'Interview', 'variant' => 'brand']), '<span class="font-semibold">86</span>'],
                    [e('Omar Nabil'), component('badge', ['label' => 'Offer', 'variant' => 'green']), '<span class="font-semibold">92</span>'],
                    [e('Lina Hassan'), component('badge', ['label' => 'Screening', 'variant' => 'amber']), '<span class="font-semibold">74</span>'],
                ],
            ]) ?>
        </div>
        <div class="space-y-2">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Tabs</p>
            <?= component('tabs', ['id' => 'ds-tabs', 'tabs' => [
                ['label' => 'Overview', 'panel' => '<p>Overview panel content.</p>'],
                ['label' => 'Activity', 'panel' => '<p>Activity panel content.</p>'],
                ['label' => 'Settings', 'panel' => '<p>Settings panel content.</p>'],
            ]]) ?>
        </div>
        <div class="grid gap-6 sm:grid-cols-2">
            <div class="space-y-2">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Accordion</p>
                <?= component('accordion', ['items' => [
                    ['title' => 'What is HalaOps?', 'content' => '<p>An enterprise HR &amp; recruitment platform.</p>', 'open' => true],
                    ['title' => 'Is it multi-tenant?', 'content' => '<p>Yes — fail-closed tenant isolation throughout.</p>'],
                ]]) ?>
            </div>
            <div class="space-y-2">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Pagination</p>
                <?= component('pagination', ['current' => 3, 'last' => 8, 'base' => url('design')]) ?>
            </div>
        </div>
        <div class="space-y-2">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Timeline</p>
            <?= component('timeline', ['items' => [
                ['title' => 'Application submitted', 'time' => '3d ago', 'variant' => 'brand', 'description' => '<p>Applied via the careers page.</p>'],
                ['title' => 'Moved to AI Screening', 'time' => '2d ago', 'variant' => 'slate'],
                ['title' => 'Interview passed', 'time' => '1d ago', 'variant' => 'green', 'description' => '<p>Score 86/100.</p>'],
            ]]) ?>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="min-w-0 space-y-2">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Calendar</p>
                <?= component('calendar', ['events' => [date('Y-m-d') => 'Today'], 'base' => url('design')]) ?>
            </div>
            <div class="min-w-0 space-y-2">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">DataGrid (sortable + selectable)</p>
                <?= component('data-grid', [
                    'columns' => [['label' => 'Candidate', 'sort' => 'name'], ['label' => 'Stage'], ['label' => 'Score', 'sort' => 'score', 'align' => 'end']],
                    'sort' => 'name', 'dir' => 'asc', 'base' => url('design'), 'selectable' => true,
                    'toolbar' => component('search', ['action' => url('design'), 'placeholder' => 'Search…', 'class' => 'w-full sm:w-56'])
                        . component('button', ['label' => 'Export', 'variant' => 'secondary', 'size' => 'sm']),
                    'rows' => [
                        [e('Sara Ali'), component('badge', ['label' => 'Interview', 'variant' => 'brand']), '<span class="font-semibold">86</span>'],
                        [e('Omar Nabil'), component('badge', ['label' => 'Offer', 'variant' => 'green']), '<span class="font-semibold">92</span>'],
                    ],
                    'footer' => component('pagination', ['current' => 1, 'last' => 4, 'base' => url('design')]),
                ]) ?>
            </div>
        </div>
    </div>
</section>

<!-- ============================== FEEDBACK ============================= -->
<section id="feedback" class="mb-10 scroll-mt-6">
    <h2 class="mb-3 text-lg font-bold text-slate-900 dark:text-white">Feedback</h2>
    <div class="card card-body space-y-6">
        <div class="space-y-3">
            <?= component('alert', ['variant' => 'success', 'title' => 'Saved', 'message' => 'Your changes were saved.']) ?>
            <?= component('alert', ['variant' => 'warning', 'message' => 'Your trial ends in 3 days.']) ?>
            <?= component('alert', ['variant' => 'error', 'message' => 'Something went wrong. Please try again.']) ?>
            <?= component('alert', ['variant' => 'info', 'message' => 'A new version is available.', 'dismissible' => true]) ?>
        </div>
        <div class="grid gap-6 sm:grid-cols-2">
            <div class="space-y-4">
                <?= component('progress', ['value' => 70, 'label' => 'Profile completeness', 'showValue' => true]) ?>
                <?= component('progress', ['value' => 35, 'label' => 'Storage', 'variant' => 'warning', 'showValue' => true]) ?>
            </div>
            <div class="space-y-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Skeleton &amp; spinner</p>
                <?= component('skeleton', ['lines' => 3]) ?>
                <?= component('spinner', ['label' => 'Loading…']) ?>
            </div>
        </div>
        <?= $demo('Tooltip &amp; toast',
            component('tooltip', ['text' => 'This is a tooltip', 'label' => 'Hover me'])
            . component('button', ['label' => 'Error toast', 'variant' => 'secondary', 'attributes' => ['data-toast-demo' => true, 'data-toast-variant' => 'error', 'data-toast-title' => 'Failed', 'data-toast-message' => 'Could not save.']])
        ) ?>
        <div class="space-y-2">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Toast (static)</p>
            <?= component('toast', ['variant' => 'success', 'title' => 'Uploaded', 'message' => 'CV uploaded successfully.']) ?>
        </div>
    </div>
</section>

<!-- ============================== OVERLAYS ============================= -->
<section id="overlays" class="mb-10 scroll-mt-6">
    <h2 class="mb-3 text-lg font-bold text-slate-900 dark:text-white">Overlays</h2>
    <div class="card card-body space-y-6">
        <?= $demo('Triggers',
            component('button', ['label' => 'Open modal', 'attributes' => ['data-modal-open' => 'ds-modal']])
            . component('button', ['label' => 'Open drawer', 'variant' => 'secondary', 'attributes' => ['data-drawer-open' => 'ds-drawer']])
            . component('dropdown', ['label' => 'Actions', 'items' => [
                ['label' => 'Edit', 'href' => '#'],
                ['label' => 'Duplicate', 'href' => '#'],
                ['divider' => true],
                ['label' => 'Delete', 'href' => '#', 'danger' => true],
            ]])
            . component('popover', ['label' => 'Popover', 'slot' => '<p class="font-medium text-slate-800 dark:text-slate-100">Popover title</p><p class="mt-1">Arbitrary rich content in a floating panel.</p>'])
        ) ?>
    </div>
</section>

<!-- ========================= SEARCH & ALERTS ========================== -->
<section id="search-alerts" class="mb-10 scroll-mt-6">
    <h2 class="mb-3 text-lg font-bold text-slate-900 dark:text-white">Search &amp; alerts</h2>
    <div class="card card-body space-y-6">
        <div class="space-y-2">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Global search</p>
            <?= component('search', ['action' => url('jobs'), 'placeholder' => 'Search jobs, candidates…', 'class' => 'max-w-md']) ?>
        </div>
        <?= $demo('Notification center',
            component('notification-center', ['items' => [
                ['title' => 'New application for Senior Engineer', 'time' => '2m', 'read' => false, 'href' => '#'],
                ['title' => 'Interview scheduled with Omar', 'time' => '1h', 'read' => false, 'href' => '#'],
                ['title' => 'Offer accepted by Sara', 'time' => 'yesterday', 'read' => true, 'href' => '#'],
            ], 'viewAllHref' => url('dashboard')])
        ) ?>
    </div>
</section>

<?= component('modal', [
    'id' => 'ds-modal', 'title' => 'Example modal',
    'slot' => '<p>Modals trap focus, close on Escape / backdrop, and lock body scroll.</p>',
    'footer' => component('button', ['label' => 'Cancel', 'variant' => 'secondary', 'attributes' => ['data-modal-close' => true]])
        . component('button', ['label' => 'Confirm']),
]) ?>

<?= component('drawer', [
    'id' => 'ds-drawer', 'title' => 'Example drawer',
    'slot' => '<p>Drawers slide in from the inline edge and mirror in RTL.</p>',
    'footer' => component('button', ['label' => 'Close', 'variant' => 'secondary', 'attributes' => ['data-drawer-close' => true]]),
]) ?>

<!-- =============================== STATES ============================== -->
<section id="states" class="mb-10 scroll-mt-6">
    <h2 class="mb-3 text-lg font-bold text-slate-900 dark:text-white">States</h2>
    <div class="grid gap-4 sm:grid-cols-2">
        <?= component('state', ['variant' => 'empty', 'title' => 'No jobs yet', 'message' => 'Create your first job to get started.', 'action' => component('button', ['label' => 'Create job', 'size' => 'sm'])]) ?>
        <?= component('state', ['variant' => 'error', 'action' => component('button', ['label' => 'Retry', 'variant' => 'secondary', 'size' => 'sm'])]) ?>
        <?= component('state', ['variant' => 'permission']) ?>
        <?= component('state', ['variant' => 'offline']) ?>
        <?= component('state', ['variant' => 'loading']) ?>
    </div>
</section>

<?php $this->endSection(); ?>
