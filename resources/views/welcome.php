<?php $this->extends('layouts.guest'); ?>
<?php $this->section('content'); ?>
<header class="mx-auto flex max-w-6xl items-center justify-between px-4 py-5">
    <div class="flex items-center gap-2">
        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 font-bold text-white">H</span>
        <span class="text-lg font-bold text-slate-900"><?= e(config('app.name')) ?></span>
    </div>
    <nav class="flex items-center gap-2">
        <a href="<?= e(url('login')) ?>" class="btn-ghost">Sign in</a>
        <a href="<?= e(url('register')) ?>" class="btn-primary">Get started</a>
    </nav>
</header>

<main>
    <section class="mx-auto max-w-6xl px-4 pt-16 pb-20 text-center">
        <span class="badge-brand mb-4">Multi-tenant SaaS • Bilingual AR/EN</span>
        <h1 class="mx-auto max-w-3xl text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl">
            Run your whole workspace on one intelligent platform
        </h1>
        <p class="mx-auto mt-5 max-w-2xl text-lg text-slate-600">
            <?= e(config('app.name')) ?> brings teams, roles, permissions, and AI-powered tooling together — securely isolated per workspace, ready out of the box.
        </p>
        <div class="mt-8 flex items-center justify-center gap-3">
            <a href="<?= e(url('register')) ?>" class="btn-primary px-6 py-3 text-base">Create your workspace</a>
            <a href="<?= e(url('login')) ?>" class="btn-secondary px-6 py-3 text-base">Sign in</a>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 pb-20">
        <div class="grid gap-6 sm:grid-cols-3">
            <?php
            $features = [
                ['Real RBAC', 'Roles, permissions, inheritance and policies — never hard-coded user types.'],
                ['True multi-tenancy', 'Every query is scoped to your workspace. Zero data leakage by design.'],
                ['Bring your own AI', 'Each workspace plugs in its own OpenAI, Anthropic, Gemini or DeepSeek keys.'],
            ];
            foreach ($features as [$title, $desc]):
            ?>
                <div class="card">
                    <div class="card-body">
                        <h3 class="font-semibold text-slate-900"><?= e($title) ?></h3>
                        <p class="mt-2 text-sm text-slate-600"><?= e($desc) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if (! empty($plans)): ?>
    <section class="mx-auto max-w-6xl px-4 pb-24">
        <h2 class="text-center text-2xl font-bold text-slate-900">Simple pricing</h2>
        <div class="mx-auto mt-8 grid max-w-md gap-6">
            <?php foreach ($plans as $plan): ?>
                <div class="card ring-2 ring-brand-500">
                    <div class="card-body text-center">
                        <h3 class="text-lg font-bold text-slate-900"><?= e($plan->name) ?></h3>
                        <p class="mt-2 text-4xl font-extrabold text-slate-900"><?= e($plan->formattedPrice()) ?>
                            <span class="text-base font-medium text-slate-500">/ <?= e($plan->interval) ?></span>
                        </p>
                        <p class="mt-3 text-sm text-slate-600"><?= e($plan->description) ?></p>
                        <a href="<?= e(url('register')) ?>" class="btn-primary mt-6 w-full">Start <?= (int) $plan->trial_days > 0 ? e($plan->trial_days) . '-day free trial' : 'now' ?></a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</main>

<footer class="border-t border-slate-200 py-8 text-center text-sm text-slate-500">
    &copy; <?= date('Y') ?> <?= e(config('app.name')) ?>. All rights reserved.
</footer>
<?php $this->endSection(); ?>
