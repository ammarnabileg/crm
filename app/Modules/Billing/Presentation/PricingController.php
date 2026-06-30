<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Presentation;

use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Billing\Application\PricingCatalog;
use HaHireAI\Modules\Workspaces\Application\PlatformContext;
use HaHireAI\Modules\Workspaces\Presentation\PlatformShell;

/**
 * Platform pricing catalog (System Owner only): the seat price and per-feature
 * prices that every workspace pays when composing a plan
 * (docs/WALLET_AND_BILLING.md §3). Workspace owners never set prices.
 */
final class PricingController
{
    public function __construct(
        private readonly PlatformShell $shell,
        private readonly PlatformContext $context,
        private readonly AuthContext $auth,
        private readonly PricingCatalog $pricing,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
    ) {
    }

    public function index(): Response
    {
        if (($r = $this->gate()) !== null) {
            return $r;
        }

        return $this->shell->render($this->context, 'admin.pricing', [
            'seatPriceCents' => $this->pricing->seatPriceCents(),
            'features' => $this->pricing->features(false),
            'status' => $this->session->pullFlash('status'),
            'error' => $this->session->pullFlash('error'),
        ]);
    }

    public function update(Request $request): Response
    {
        if (($r = $this->gate($request)) !== null) {
            return $r;
        }

        $this->pricing->setSeatPrice((int) round(((float) $request->input('seat_price', 0)) * 100));

        foreach ((array) $request->input('feature_price', []) as $key => $dollars) {
            $this->pricing->setFeaturePrice((string) $key, (int) round(((float) $dollars) * 100));
        }

        $this->audit->record('platform.pricing.updated', [
            'actor_user_id' => $this->auth->id(),
            'entity_type' => 'pricing_catalog',
        ]);
        $this->session->flash('status', 'Pricing updated.');

        return Response::redirect('/pricing');
    }

    private function gate(?Request $request = null): ?Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::html('<h1>403</h1><p>Platform access requires a System Owner.</p>', 403);
        }
        if (! $this->context->can('system.pricing.manage')) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }
        if ($request !== null && ! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>419</h1><p>Security check failed.</p>', 419);
        }

        return null;
    }
}
