<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<?php
/** @var array<int,array<string,mixed>> $automations */
/** @var string[] $triggers @var string[] $conditions @var string[] $actions */
/** @var bool $canManage */
$statusBadge = static fn (string $s): string => match ($s) {
    'success', 'completed' => 'badge badge-success',
    'failed', 'error'      => 'badge badge-danger',
    default                => 'badge',
};
?>
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">Automations</h1>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
            Build “when something happens → (optional checks) → do something” rules.
            Each rule binds a <strong>trigger</strong> event to ordered
            <strong>condition</strong> and <strong>action</strong> steps.
        </p>
    </div>

    <?php $this->include('partials.alerts'); ?>

    <?php if ($canManage): ?>
        <form method="POST" action="<?= e(url('automations')) ?>" class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
            <?= csrf_field() ?>
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">New automation</h2>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="name">Name</label>
                    <input class="input" id="name" name="name" maxlength="120" required
                           value="<?= e(old('name')) ?>" placeholder="e.g. Notify the team on a new application">
                </div>
                <div>
                    <label class="label" for="trigger_event">When this happens (trigger)</label>
                    <select class="input" id="trigger_event" name="trigger_event" required>
                        <option value="">Choose a trigger…</option>
                        <?php foreach ($triggers as $t): ?>
                            <option value="<?= e($t) ?>" <?= old('trigger_event') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <fieldset class="mt-5">
                <legend class="text-sm font-medium text-slate-700 dark:text-slate-300">Then run these steps</legend>
                <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">
                    Conditions must pass for the actions to run. Configuration is optional JSON
                    (e.g. <code>{"message": "New application received"}</code> for a notify action).
                </p>

                <div data-steps class="space-y-3">
                    <?php for ($i = 0; $i < 2; $i++): ?>
                        <div class="grid gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-[200px_1fr] dark:border-slate-800" data-step-row>
                            <select class="input" name="steps[<?= $i ?>][op]" aria-label="Step <?= $i + 1 ?> operation">
                                <option value="">— skip —</option>
                                <optgroup label="Condition">
                                    <?php foreach ($conditions as $c): ?>
                                        <option value="condition:<?= e($c) ?>">condition · <?= e($c) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Action">
                                    <?php foreach ($actions as $a): ?>
                                        <option value="action:<?= e($a) ?>">action · <?= e($a) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                            <input class="input font-mono text-xs" name="steps[<?= $i ?>][config]"
                                   placeholder='Optional config JSON, e.g. {"message":"Hi"}'>
                        </div>
                    <?php endfor; ?>
                </div>

                <template data-step-template>
                    <div class="grid gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-[200px_1fr] dark:border-slate-800" data-step-row>
                        <select class="input" name="steps[__i__][op]" aria-label="Step operation">
                            <option value="">— skip —</option>
                            <optgroup label="Condition">
                                <?php foreach ($conditions as $c): ?>
                                    <option value="condition:<?= e($c) ?>">condition · <?= e($c) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Action">
                                <?php foreach ($actions as $a): ?>
                                    <option value="action:<?= e($a) ?>">action · <?= e($a) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                        <input class="input font-mono text-xs" name="steps[__i__][config]"
                               placeholder='Optional config JSON, e.g. {"message":"Hi"}'>
                    </div>
                </template>

                <button type="button" class="btn-secondary mt-3" data-add-step>+ Add step</button>
            </fieldset>

            <div class="mt-5">
                <button type="submit" class="btn-primary">Create automation</button>
            </div>
        </form>
    <?php endif; ?>

    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
            <thead class="bg-slate-50 text-left text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-3 font-medium">Name</th>
                    <th class="px-4 py-3 font-medium">Trigger</th>
                    <th class="px-4 py-3 font-medium">Steps</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Last run</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php if ($automations === []): ?>
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                        No automations yet. <?= $canManage ? 'Create your first one above.' : '' ?>
                    </td></tr>
                <?php endif; ?>
                <?php foreach ($automations as $a): ?>
                    <tr>
                        <td class="px-4 py-3">
                            <a class="font-medium text-brand-600 hover:underline dark:text-brand-400" href="<?= e(url('automations/show?id=' . $a['id'])) ?>"><?= e($a['name']) ?></a>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-300"><?= e($a['trigger_event']) ?></td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-300"><?= e((string) $a['step_count']) ?></td>
                        <td class="px-4 py-3">
                            <span class="<?= $a['is_active'] ? 'badge badge-success' : 'badge' ?>"><?= $a['is_active'] ? 'Active' : 'Paused' ?></span>
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                            <?php if ($a['last_run'] !== null): ?>
                                <span class="<?= e($statusBadge((string) $a['last_run']['status'])) ?>"><?= e((string) $a['last_run']['status']) ?></span>
                                <span class="ms-1 text-xs"><?= e((string) $a['last_run']['at']) ?></span>
                            <?php else: ?>
                                <span class="text-xs">never</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>

<?php $this->section('scripts'); ?>
<script nonce="<?= e(csp_nonce()) ?>">
  // Progressive enhancement: clone a blank step row. Works without JS too — the
  // form ships two blank rows and the server skips any left empty.
  (function () {
    var btn = document.querySelector('[data-add-step]');
    var tpl = document.querySelector('[data-step-template]');
    var box = document.querySelector('[data-steps]');
    if (!btn || !tpl || !box) return;
    var i = box.querySelectorAll('[data-step-row]').length;
    btn.addEventListener('click', function () {
      var html = tpl.innerHTML.replace(/__i__/g, String(i++));
      var frag = document.createElement('div');
      frag.innerHTML = html.trim();
      box.appendChild(frag.firstChild);
    });
  })();
</script>
<?php $this->endSection(); ?>
