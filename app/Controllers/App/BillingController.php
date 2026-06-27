<?php

declare(strict_types=1);

namespace App\Controllers\App;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Billing\BillingService;
use App\Services\Billing\StripeGateway;

/**
 * Billing — in-app subscription management for the current workspace. The index
 * shows the current subscription, available plans (with a subscribe/switch button)
 * and recent invoices; subscribe runs the manual/in-app path that always works
 * with zero gateway keys. The webhook is an external callback (registered OUTSIDE
 * the auth/tenant group, CSRF-exempt) that simply logs gateway events and stays
 * inert-but-safe when no signing secret is configured.
 *
 * Reads require billing.view; subscribe requires billing.manage; the webhook has
 * NO auth/permission by design (it carries a gateway signature instead).
 */
final class BillingController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(can('billing.view'), 403);

        $billing = new BillingService();
        $current = $billing->currentSubscription();

        return $this->view('app.billing.index', [
            'title'             => 'Billing',
            'subscription'      => $current,
            'currentPlanId'     => $current !== null ? (int) $current->getAttribute('plan_id') : 0,
            'plans'             => $billing->availablePlans(),
            'invoices'          => $billing->invoices(),
            'gatewayConfigured' => $billing->gatewayConfigured(),
            'canManage'         => can('billing.manage'),
        ]);
    }

    public function subscribe(Request $request): Response
    {
        abort_unless(can('billing.manage'), 403);

        $data = $this->validate($request, [
            'plan_id' => 'required|integer',
        ]);

        $billing = new BillingService();

        // The manual/in-app subscribe always completes. When an online gateway is
        // configured and the plan is paid, a hosted checkout COULD be initiated here
        // instead; we deliberately keep the always-working in-app path so billing is
        // never blocked on external keys (degrade gracefully).
        $billing->subscribe((int) $data['plan_id']);

        $this->withSuccess('Subscription updated.');

        return $this->redirect(url('billing'));
    }

    /**
     * External gateway webhook. NO auth, NO permission, CSRF-exempt — it is a
     * server-to-server callback that authenticates via the gateway signature, not
     * our session/CSRF token. Records every event for audit; verifies the signature
     * only when a signing secret is configured. With zero keys it accepts and logs
     * the event unverified and returns 200, so it is safe and inert out of the box.
     */
    public function webhook(Request $request): Response
    {
        $payload = $this->rawBody($request);
        $sigHeader = (string) $request->header('stripe-signature', '');

        $gateway = new StripeGateway();
        $verified = $gateway->verifyWebhook($payload, $sigHeader);

        // If a signing secret IS configured but verification fails, reject (400) and
        // do not trust the event. Without a signing secret we cannot verify, so we
        // accept-but-flag-unverified (is_verified = 0) and still log it.
        if (! $verified && $gateway->webhookSigningConfigured()) {
            return Response::json(['ok' => false, 'error' => 'invalid_signature'], 400);
        }

        // gateway_events.payload is a JSON column (CHECK valid JSON). A legitimate
        // gateway event is always a JSON object; anything else is a bad request and
        // must NOT reach the insert (it would violate the constraint).
        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            return Response::json(['ok' => false, 'error' => 'invalid_payload'], 400);
        }

        $gatewayId = (int) app('db')->table('payment_gateways')
            ->where('key', '=', 'stripe')
            ->value('id');

        app('db')->table('gateway_events')->insert([
            'uuid'               => \App\Core\Model::generateUuid(),
            'payment_gateway_id' => $gatewayId,
            'workspace_id'       => null,
            'payment_id'         => null,
            'external_event_id'  => (string) ($decoded['id'] ?? \App\Core\Model::generateUuid()),
            'event_type'         => (string) ($decoded['type'] ?? 'unknown'),
            'payload'            => $payload,
            'signature'          => $sigHeader !== '' ? $sigHeader : null,
            'is_verified'        => $verified ? 1 : 0,
            'received_at'        => now(),
            'created_at'         => now(),
        ]);

        return Response::json(['ok' => true, 'verified' => $verified], 200);
    }

    /**
     * The raw request body for signature verification. Stripe POSTs raw JSON, so we
     * prefer the unparsed stream; to stay testable without real HTTP we also accept
     * an injected `payload` body field (used by the feature test) before falling
     * back to php://input. Never throws.
     */
    private function rawBody(Request $request): string
    {
        // Read the unparsed stream FIRST. Stripe POSTs raw JSON, and signature
        // verification needs the exact bytes. We must read php://input before any
        // Request accessor that would parse a JSON body and consume the stream.
        $raw = @file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            return $raw;
        }

        // Test/integration fallback: an explicitly provided raw payload field.
        $injected = $request->input('payload');

        return is_string($injected) ? $injected : '';
    }
}
