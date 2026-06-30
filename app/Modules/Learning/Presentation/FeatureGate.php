<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Learning\Presentation;

use HaHireAI\Core\Contracts\EntitlementResolver;
use HaHireAI\Core\Http\Response;

/**
 * The Learning feature gate. Learning is a **paid add-on**, not a basic-plan
 * capability: every Learning route is allowed only when the workspace's composed
 * plan enables the `learning` feature. When billing is disabled / there is no
 * plan, `gateFeatures()` returns null and we do not gate (the platform works
 * out of the box). Disabling the add-on **never deletes data** — it only blocks
 * access until the subscription re-enables it.
 */
final class FeatureGate
{
    public const FEATURE = 'learning';

    /** Returns an upgrade Response when the feature is off, or null when allowed. */
    public static function check(EntitlementResolver $entitlements, string $workspaceId): ?Response
    {
        $features = $entitlements->gateFeatures($workspaceId);
        if ($features === null || in_array(self::FEATURE, $features, true)) {
            return null;
        }

        return Response::html(self::upgradeHtml(), 402);
    }

    private static function upgradeHtml(): string
    {
        return <<<'HTML'
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Learning — add-on required</title><script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-slate-50">
<div class="mx-auto flex min-h-screen max-w-lg flex-col items-center justify-center px-6 text-center">
  <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-indigo-50 text-2xl">🎓</div>
    <h1 class="text-xl font-semibold text-slate-900">Learning &amp; Development is an add-on</h1>
    <p class="mt-2 text-sm text-slate-500">Build training, onboarding and development programs — with sections, lessons, quizzes, to-dos, assignment and progress tracking.</p>
    <p class="mt-2 text-sm text-slate-500">Your current plan doesn't include it. Any programs you've already created are safe and will reappear the moment it's re-enabled.</p>
    <a href="/billing" class="mt-5 inline-block rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Enable in Billing</a>
    <a href="/dashboard" class="mt-3 block text-xs text-slate-400 hover:text-slate-600">Back to dashboard</a>
  </div>
</div></body></html>
HTML;
    }
}
