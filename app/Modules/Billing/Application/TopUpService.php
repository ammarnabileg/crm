<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Application;

use HaHireAI\Core\Contracts\EventDispatcher;
use HaHireAI\Core\Database\Connection;
use HaHireAI\Modules\Billing\Application\Exceptions\BillingException;
use HaHireAI\Modules\Billing\Contracts\HostedCheckoutGateway;
use HaHireAI\Shared\Ulid;

/**
 * Wallet top-ups via the hosted-checkout gateway (Fawaterak) — the only path by
 * which real money enters (docs/WALLET_AND_BILLING.md §9). A top-up is recorded,
 * the Owner pays on the provider's page, and a signed webhook credits the wallet
 * exactly once (idempotent on the provider invoice id).
 */
final class TopUpService
{
    public const MIN_CENTS = 100;      // $1.00
    public const MAX_CENTS = 100_000_00; // $100,000

    public function __construct(
        private readonly Connection $connection,
        private readonly HostedCheckoutGateway $gateway,
        private readonly WalletService $wallet,
        private readonly EventDispatcher $events,
    ) {
    }

    public function gatewayEnabled(): bool
    {
        return $this->gateway->enabled();
    }

    /**
     * Begin a top-up: record a pending payment, open a checkout session and return
     * where to pay.
     *
     * @return array{iframe_url:string, payment_id:string}
     */
    public function start(string $workspaceId, int $amountCents, ?string $actorUserId = null): array
    {
        if ($amountCents < self::MIN_CENTS || $amountCents > self::MAX_CENTS) {
            throw new BillingException('Enter an amount between $1 and $100,000.');
        }

        $id = Ulid::generate();
        $now = gmdate('Y-m-d H:i:s');
        $this->connection->statement(
            'INSERT INTO fawaterak_payments (id, workspace_id, amount_cents, currency, status, actor_user_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $workspaceId, $amountCents, 'USD', 'pending', $actorUserId, $now, $now],
        );

        $session = $this->gateway->createTopUpSession($workspaceId, $amountCents, 'USD', ['payment_id' => $id, 'workspace_id' => $workspaceId]);
        $this->connection->statement(
            'UPDATE fawaterak_payments SET provider_invoice_id = ?, updated_at = ? WHERE id = ?',
            [(string) $session['invoice_id'], gmdate('Y-m-d H:i:s'), $id],
        );

        return ['iframe_url' => (string) $session['iframe_url'], 'payment_id' => $id];
    }

    /**
     * Handle an inbound provider webhook: verify the signature, then credit the
     * wallet once. Returns true when a credit was applied.
     *
     * @param  array<string,mixed>  $payload
     */
    public function handleWebhook(array $payload, string $signature): bool
    {
        if (! $this->gateway->verifySignature($payload, $signature)) {
            return false;
        }

        $parsed = $this->gateway->parseWebhook($payload);
        $payment = $this->connection->selectOne(
            'SELECT * FROM fawaterak_payments WHERE provider_invoice_id = ?',
            [$parsed['invoice_id']],
        );
        if ($payment === null) {
            return false;
        }

        $this->connection->statement(
            'UPDATE fawaterak_payments SET signature_verified = 1, raw_payload = ?, updated_at = ? WHERE id = ?',
            [json_encode($payload), gmdate('Y-m-d H:i:s'), (string) $payment['id']],
        );

        if (! $parsed['paid']) {
            $this->connection->statement(
                "UPDATE fawaterak_payments SET status = 'failed', updated_at = ? WHERE id = ?",
                [gmdate('Y-m-d H:i:s'), (string) $payment['id']],
            );

            return false;
        }

        return $this->creditPayment($payment);
    }

    /**
     * Confirm an offline/simulated top-up (only valid when no live gateway is
     * configured). Used by the internal simulate checkout so the platform works
     * without the PSP.
     */
    public function confirmSimulated(string $paymentId, ?string $actorUserId = null): bool
    {
        if ($this->gateway->enabled()) {
            throw new BillingException('Simulated top-ups are disabled while a live gateway is configured.');
        }
        $payment = $this->connection->selectOne('SELECT * FROM fawaterak_payments WHERE id = ?', [$paymentId]);
        if ($payment === null) {
            throw new BillingException('Top-up not found.');
        }

        return $this->creditPayment($payment, $actorUserId);
    }

    /** @return array<string,mixed>|null */
    public function find(string $paymentId): ?array
    {
        return $this->connection->selectOne('SELECT * FROM fawaterak_payments WHERE id = ?', [$paymentId]);
    }

    /**
     * Credit the wallet for a paid top-up exactly once (idempotent guard on the
     * `credited` flag).
     *
     * @param  array<string,mixed>  $payment
     */
    private function creditPayment(array $payment, ?string $actorUserId = null): bool
    {
        if ((int) ($payment['credited'] ?? 0) === 1) {
            return false; // already applied — idempotent
        }

        $workspaceId = (string) $payment['workspace_id'];
        $amount = (int) $payment['amount_cents'];

        $balance = $this->wallet->credit(
            $workspaceId,
            $amount,
            'fawaterak',
            (string) $payment['id'],
            'Wallet top-up',
            $actorUserId ?? ($payment['actor_user_id'] ?? null),
        );

        $this->connection->statement(
            "UPDATE fawaterak_payments SET status = 'paid', credited = 1, updated_at = ? WHERE id = ?",
            [gmdate('Y-m-d H:i:s'), (string) $payment['id']],
        );

        $this->events->dispatch('wallet.credited', [
            'workspace_id' => $workspaceId,
            'amount_cents' => $amount,
            'balance_cents' => $balance,
        ]);

        return true;
    }
}
