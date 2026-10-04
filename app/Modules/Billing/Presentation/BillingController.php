<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Presentation;

use HaHireAI\Core\Contracts\AuditRecorder;
use HaHireAI\Core\Http\Request;
use HaHireAI\Core\Http\Response;
use HaHireAI\Core\Http\Session;
use HaHireAI\Modules\Authentication\Application\AuthContext;
use HaHireAI\Modules\Billing\Application\Exceptions\BillingException;
use HaHireAI\Modules\Billing\Application\InvoiceService;
use HaHireAI\Modules\Billing\Application\PlanComposer;
use HaHireAI\Modules\Billing\Application\PricingCatalog;
use HaHireAI\Modules\Billing\Application\SeatCounter;
use HaHireAI\Modules\Billing\Application\TopUpService;
use HaHireAI\Modules\Billing\Application\WalletService;
use HaHireAI\Modules\Billing\Application\WorkspacePlanService;
use HaHireAI\Modules\Workspaces\Application\WorkspaceContext;
use HaHireAI\Modules\Workspaces\Presentation\WorkspaceShell;

/**
 * Workspace billing: the prepaid wallet, the composed monthly plan (seats +
 * features), add-ons, top-ups and history (docs/WALLET_AND_BILLING.md). Every
 * action is permission-gated (keys, never roles) and CSRF-protected.
 */
final class BillingController
{
    public function __construct(
        private readonly WorkspaceShell $shell,
        private readonly WorkspaceContext $context,
        private readonly AuthContext $auth,
        private readonly Session $session,
        private readonly AuditRecorder $audit,
        private readonly WalletService $wallet,
        private readonly PricingCatalog $pricing,
        private readonly WorkspacePlanService $plans,
        private readonly PlanComposer $composer,
        private readonly TopUpService $topup,
        private readonly SeatCounter $seats,
        private readonly InvoiceService $invoices,
    ) {
    }

    public function index(): Response
    {
        if (($r = $this->gate('billing.view')) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $plan = $this->plans->find($ws);
        $activeFeatures = $this->plans->activeFeatureKeys($ws);

        return $this->shell->render($this->context, 'billing.wallet', [
            'balanceCents' => $this->wallet->balance($ws),
            'plan' => $plan,
            'seatPriceCents' => $this->pricing->seatPriceCents(),
            'billableSeats' => $this->seats->billableSeats($ws),
            'coveredSeats' => $this->plans->coveredSeats($ws),
            'premiumFeatures' => $this->pricing->premiumFeatures(),
            'activeFeatures' => $activeFeatures,
            'transactions' => $this->wallet->transactions($ws, 25),
            'invoices' => $this->invoices->listForWorkspace($ws, 12),
            'canManage' => $this->context->can('billing.manage'),
            'gatewayEnabled' => $this->topup->gatewayEnabled(),
            'status' => $this->session->pullFlash('status'),
            'error' => $this->session->pullFlash('error'),
        ]);
    }

    /** Start a wallet top-up: open a Fawaterak session and go to the checkout. */
    public function topup(Request $request): Response
    {
        if (($r = $this->gate('billing.wallet.topup', $request)) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $amountCents = (int) round(((float) $request->input('amount', 0)) * 100);

        try {
            $session = $this->topup->start($ws, $amountCents, $this->auth->id());
        } catch (BillingException $e) {
            $this->session->flash('error', $e->getMessage());

            return Response::redirect('/billing');
        }

        $this->audit->record('billing.wallet.topup_started', [
            'workspace_id' => $ws, 'actor_user_id' => $this->auth->id(),
            'entity_type' => 'wallet', 'changes' => ['amount_cents' => $amountCents],
        ]);

        return Response::redirect($session['iframe_url']);
    }

    /** Offline simulate confirm (only when no live gateway is configured). */
    public function topupSimulate(Request $request, string $paymentId): Response
    {
        if (($r = $this->gate('billing.wallet.topup', $request)) !== null) {
            return $r;
        }

        // GET shows a confirm button; POST applies the credit.
        if (! $request->isMethod('POST')) {
            $payment = $this->topup->find($paymentId);

            return $this->shell->render($this->context, 'billing.simulate', [
                'payment' => $payment,
            ]);
        }

        try {
            $this->topup->confirmSimulated($paymentId, $this->auth->id());
            $this->session->flash('status', 'Wallet topped up.');
        } catch (BillingException $e) {
            $this->session->flash('error', $e->getMessage());
        }

        return Response::redirect('/billing');
    }

    /** Compose / re-activate the monthly plan (seats + features), paid from the wallet. */
    public function compose(Request $request): Response
    {
        if (($r = $this->gate('billing.plan.compose', $request)) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $seats = (int) $request->input('seats', 0);
        $features = array_values(array_map('strval', (array) $request->input('features', [])));

        try {
            $this->composer->compose($ws, $seats, $features, $this->auth->id());
            $this->session->flash('status', 'Your plan is active.');
        } catch (BillingException $e) {
            $this->session->flash('error', $e->getMessage());

            return Response::redirect('/billing');
        }

        $this->audit->record('billing.plan.composed', [
            'workspace_id' => $ws, 'actor_user_id' => $this->auth->id(),
            'entity_type' => 'workspace_plan', 'changes' => ['seats' => $seats, 'features' => $features],
        ]);

        return Response::redirect('/billing');
    }

    /**
     * Upgrade or downgrade the composed plan mid-term. Only the remaining slice of
     * the term is settled now, pro-rata: an upgrade is charged, a downgrade is
     * credited back to the wallet (docs/WALLET_AND_BILLING.md §14).
     */
    public function changePlan(Request $request): Response
    {
        if (($r = $this->gate('billing.plan.compose', $request)) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $seats = (int) $request->input('seats', 0);
        $features = array_values(array_map('strval', (array) $request->input('features', [])));

        try {
            $this->composer->changePlan($ws, $seats, $features, $this->auth->id());
            $this->session->flash('status', 'Your plan has been updated. The change was prorated for this term.');
        } catch (BillingException $e) {
            $this->session->flash('error', $e->getMessage());

            return Response::redirect('/billing');
        }

        $this->audit->record('billing.plan.changed', [
            'workspace_id' => $ws, 'actor_user_id' => $this->auth->id(),
            'entity_type' => 'workspace_plan', 'changes' => ['seats' => $seats, 'features' => $features],
        ]);

        return Response::redirect('/billing');
    }

    /** Add one billable seat at a full month's price (expires with the plan). */
    public function addSeat(Request $request): Response
    {
        if (($r = $this->gate('billing.seats.manage', $request)) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        try {
            $this->composer->addSeat($ws, $this->auth->id());
            $this->session->flash('status', 'Seat added for this month.');
        } catch (BillingException $e) {
            $this->session->flash('error', $e->getMessage());
        }

        return Response::redirect('/billing');
    }

    /** Activate an add-on feature mid-term (charged now, expires with the plan). */
    public function addAddon(Request $request): Response
    {
        if (($r = $this->gate('billing.addons.manage', $request)) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $feature = (string) $request->input('feature', '');
        try {
            $this->composer->addAddonFeature($ws, $feature, $this->auth->id());
            $this->session->flash('status', 'Add-on activated.');
        } catch (BillingException $e) {
            $this->session->flash('error', $e->getMessage());
        }

        return Response::redirect('/billing');
    }

    /** Turn auto-renew on/off for the composed plan. */
    public function autoRenew(Request $request): Response
    {
        if (($r = $this->gate('billing.manage', $request)) !== null) {
            return $r;
        }

        $ws = (string) $this->context->workspaceId();
        $plan = $this->plans->find($ws);
        if ($plan !== null) {
            $on = $request->input('auto_renew') !== null;
            $this->plans->update((string) $plan['id'], ['auto_renew' => $on]);
            $this->session->flash('status', $on ? 'Auto-renew enabled.' : 'Auto-renew disabled.');
        }

        return Response::redirect('/billing');
    }

    private function gate(string $permission, ?Request $request = null): ?Response
    {
        if (! $this->auth->check()) {
            return Response::redirect('/login');
        }
        if (! $this->context->resolve()) {
            return Response::redirect('/dashboard');
        }
        if (! $this->context->can($permission)) {
            return Response::html('<h1>403</h1><p>You do not have permission.</p>', 403);
        }
        if ($request !== null && ! $this->session->verifyCsrf((string) $request->input('_csrf'))) {
            return Response::html('<h1>419</h1><p>Security check failed.</p>', 419);
        }

        return null;
    }
}
