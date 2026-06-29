<?php
/** @var list<array<string,mixed>> $catalog */
/** @var list<string> $categories */
/** @var array<string,mixed>|null $workflow */
/** @var string $csrf */

$wfId = $workflow['id'] ?? '';
$wfName = (string) ($workflow['name'] ?? 'Untitled workflow');
$wfEnabled = $workflow === null ? true : ((int) $workflow['enabled'] === 1);

// Safe to embed inside <script> tags even with arbitrary user text in node config.
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
$initialGraph = json_encode([
    'nodes' => $workflow['nodes'] ?? [],
    'edges' => $workflow['edges'] ?? [],
], $jsonFlags);
?>
<div id="wf-builder" class="flex h-full flex-col bg-slate-50"
     data-save-url="/workflows/save"
     data-csrf="<?= e($csrf) ?>"
     data-id="<?= e($wfId) ?>"
     data-enabled="<?= $wfEnabled ? '1' : '0' ?>">

    <!-- Toolbar -->
    <div class="flex h-14 items-center gap-3 border-b border-slate-200 bg-white px-4">
        <a href="/workflows" data-pjax class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100" title="Back to workflows">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        </a>
        <input id="wf-name" value="<?= e($wfName) ?>" class="w-64 rounded-lg border border-transparent px-2 py-1.5 text-sm font-semibold text-slate-900 hover:border-slate-200 focus:border-indigo-400 focus:outline-none" placeholder="Workflow name">
        <span id="wf-status" class="rounded-full px-2.5 py-0.5 text-xs font-medium"></span>
        <div class="ms-auto flex items-center gap-2">
            <label class="flex items-center gap-1.5 text-xs font-medium text-slate-500">
                <input type="checkbox" id="wf-enabled" <?= $wfEnabled ? 'checked' : '' ?> class="rounded border-slate-300"> Enabled
            </label>
            <button id="wf-save" class="rounded-lg bg-indigo-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
        </div>
    </div>

    <div class="flex flex-1 overflow-hidden">
        <!-- Palette -->
        <div class="w-60 shrink-0 overflow-y-auto border-e border-slate-200 bg-white">
            <div class="sticky top-0 border-b border-slate-100 bg-white p-3">
                <input id="wf-search" class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm" placeholder="Search nodes…">
            </div>
            <div id="wf-palette" class="p-2"></div>
        </div>

        <!-- Canvas -->
        <div id="wf-canvas" class="relative flex-1 overflow-hidden" style="background-color:#f8fafc;background-image:radial-gradient(#e2e8f0 1px,transparent 1px);background-size:22px 22px;">
            <div id="wf-layer" class="absolute left-0 top-0 origin-top-left" style="width:6000px;height:4000px;">
                <svg id="wf-edges" class="pointer-events-none absolute left-0 top-0 h-full w-full" style="overflow:visible;"></svg>
                <div id="wf-nodes" class="absolute left-0 top-0"></div>
            </div>
            <div id="wf-empty" class="pointer-events-none absolute inset-0 flex items-center justify-center text-center">
                <div class="text-slate-400">
                    <svg class="mx-auto mb-2 h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <p class="text-sm font-medium">Drag a trigger here to begin</p>
                    <p class="text-xs">then connect actions to automate your hiring</p>
                </div>
            </div>
            <!-- Zoom controls -->
            <div class="absolute bottom-4 left-4 flex items-center gap-1 rounded-lg border border-slate-200 bg-white p-1 shadow-sm">
                <button data-zoom="out" class="rounded px-2 py-1 text-slate-600 hover:bg-slate-100">−</button>
                <span id="wf-zoom" class="w-12 text-center text-xs text-slate-500">100%</span>
                <button data-zoom="in" class="rounded px-2 py-1 text-slate-600 hover:bg-slate-100">+</button>
                <button data-zoom="fit" class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-slate-100" title="Reset view">Reset</button>
            </div>
            <!-- Minimap -->
            <div class="absolute bottom-4 right-4 h-24 w-40 overflow-hidden rounded-lg border border-slate-200 bg-white/80 shadow-sm">
                <svg id="wf-minimap" class="h-full w-full"></svg>
            </div>
        </div>

        <!-- Inspector -->
        <div class="w-72 shrink-0 overflow-y-auto border-s border-slate-200 bg-white">
            <div id="wf-inspector" class="p-4"></div>
            <div class="border-t border-slate-100 p-4">
                <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">In plain language</h3>
                <p id="wf-summary" class="text-sm leading-relaxed text-slate-600"></p>
            </div>
        </div>
    </div>

    <!-- Hidden submit form -->
    <form id="wf-form" method="post" action="/workflows/save" class="hidden">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" value="<?= e($wfId) ?>">
        <input type="hidden" name="name" id="wf-form-name">
        <input type="hidden" name="enabled" id="wf-form-enabled" value="1">
        <input type="hidden" name="graph" id="wf-form-graph">
    </form>

    <script type="application/json" id="wf-catalog"><?= json_encode($catalog, $jsonFlags) ?></script>
    <script type="application/json" id="wf-categories"><?= json_encode($categories, $jsonFlags) ?></script>
    <script type="application/json" id="wf-initial"><?= $initialGraph ?></script>
</div>
<script src="/assets/workflow-builder.js"></script>
