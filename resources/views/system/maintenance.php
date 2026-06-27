<?php $this->extends('layouts.app'); ?>
<?php $this->section('content'); ?>
<div class="mx-auto max-w-2xl space-y-6">
    <h1 class="text-2xl font-bold text-slate-900"><?= e($title) ?></h1>

    <div class="card">
        <div class="card-body space-y-4">
            <?php $this->include('partials.alerts'); ?>

            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-700">Current status</p>
                    <p class="text-xs text-slate-400">When ON, only super admins can use the dashboard.</p>
                </div>
                <?php if ($state['enabled']): ?>
                    <span class="badge-amber">Enabled</span>
                <?php else: ?>
                    <span class="badge-slate">Disabled</span>
                <?php endif; ?>
            </div>

            <?php if ($state['enabled']): ?>
                <dl class="grid grid-cols-1 gap-2 rounded-lg bg-slate-50 p-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-slate-500">Enabled since</dt>
                        <dd class="font-medium text-slate-900"><?= e($state['since'] ?? '—') ?></dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Allowed IP</dt>
                        <dd class="font-medium text-slate-900"><?= e($state['allow_ip'] ?? 'None') ?></dd>
                    </div>
                    <?php if (($state['message'] ?? '') !== ''): ?>
                        <div class="sm:col-span-2">
                            <dt class="text-slate-500">Message shown to visitors</dt>
                            <dd class="font-medium text-slate-900"><?= e($state['message']) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="<?= e(url('system/maintenance')) ?>" class="space-y-4">
                <?= csrf_field() ?>

                <div>
                    <label class="label" for="message">Maintenance message <span class="font-normal text-slate-400">(optional)</span></label>
                    <textarea class="input" id="message" name="message" rows="3" placeholder="We'll be back shortly. Thanks for your patience."><?= e(old('message', $state['message'] ?? '')) ?></textarea>
                    <p class="mt-1 text-xs text-slate-400">Shown to visitors on the maintenance page.</p>
                </div>

                <div>
                    <label class="label" for="allow_ip">Allow IP through <span class="font-normal text-slate-400">(optional)</span></label>
                    <input class="input" id="allow_ip" name="allow_ip" value="<?= e(old('allow_ip', $state['allow_ip'] ?? '')) ?>" placeholder="e.g. 203.0.113.5">
                    <p class="mt-1 text-xs text-slate-400">This address can keep browsing while maintenance is on.</p>
                </div>

                <div class="flex flex-wrap gap-3 pt-2">
                    <button type="submit" name="mode" value="enable" class="btn-primary">Enable maintenance</button>
                    <button type="submit" name="mode" value="disable" class="btn-secondary">Disable maintenance</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
