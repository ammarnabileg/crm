<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Billing\Application;

use HaHireAI\Core\Contracts\EventDispatcher;
use HaHireAI\Modules\Billing\Application\Exceptions\BillingException;

/**
 * Composes, renews and extends the per-workspace monthly plan, charging the
 * wallet (docs/WALLET_AND_BILLING.md §4–§7). Money never leaves the wallet here;
 * the wallet is funded only by the Fawaterak top-up. Every charge is a wallet
 * debit + an immutable invoice, and a billing domain event for the Workflow
 * product.
 */
final class PlanComposer
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly PricingCatalog $pricing,
        private readonly WorkspacePlanService $plans,
        private readonly SeatCounter $seats,
        private readonly InvoiceService $invoices,
        private readonly EventDispatcher $events,
    ) {
    }

    /**
     * Premium-feature price map for the chosen keys (basics resolve to 0 and are
     * dropped from the snapshot).
     *
     * @param  list<string>  $featureKeys
     * @return array<string,int>  featureKey => price_cents (premium only)
     */
    public function featurePrices(array $featureKeys): array
    {
        $map = [];
        foreach (array_unique($featureKeys) as $key) {
            $price = $this->pricing->featurePriceCents((string) $key);
            if ($price > 0) {
                $map[(string) $key] = $price;
            }
        }

        return $map;
    }

    /** Monthly cost in cents for $seats billable seats + the chosen features. */
    public function monthlyCost(int $seats, array $featureKeys): int
    {
        $seats = max(0, $seats);

        return $seats * $this->pricing->seatPriceCents() + array_sum($this->featurePrices($featureKeys));
    }

    /**
     * Activate (or re-activate) the workspace's monthly plan: pick the number of
     * billable seats and the premium features. The composed cost is debited from
     * the wallet (rejected when short), an invoice is issued, and the term runs one
     * month from now. Seats are clamped up to the current staff count so existing
     * members are always covered.
     *
     * @param  list<string>  $featureKeys
     */
    public function compose(string $workspaceId, int $seats, array $featureKeys, ?string $actorUserId = null, ?int $nowTs = null): void
    {
        $nowTs ??= time();
        $seats = max($seats, $this->seats->billableSeats($workspaceId));
        $features = $this->featurePrices($featureKeys);
        $cost = $seats * $this->pricing->seatPriceCents() + array_sum($features);

        if (! $this->wallet->canAfford($workspaceId, $cost)) {
            throw new BillingException('Not enough credits. Please top up the wallet first.');
        }

        $planId = $this->plans->ensure($workspaceId);
        if ($cost > 0) {
            $this->wallet->debit($workspaceId, $cost, 'plan', $planId, 'Monthly plan', $actorUserId);
        }

        $periodStart = $this->sql($nowTs);
        $periodEnd = $this->monthEnd($nowTs);
        $this->plans->update($planId, [
            'status' => 'active',
            'seats_paid' => $seats,
            'monthly_cost_cents' => $cost,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'locked_at' => null,
        ]);
        $this->plans->setFeatures($workspaceId, $planId, $features);
        $this->issueInvoice($workspaceId, $cost, $periodStart, $periodEnd, 'Monthly plan', $seats, array_keys($features));

        $this->events->dispatch('plan.activated', [
            'workspace_id' => $workspaceId, 'seats' => $seats, 'features' => array_keys($features), 'cost_cents' => $cost,
        ]);
        $this->maybeLowBalance($workspaceId);
    }

    /**
     * Renew the current plan from the wallet (driven by the lifecycle tick). On
     * success the term rolls forward a month; on insufficient credits the plan is
     * locked. Add-ons are not renewed — they expire with the previous term.
     *
     * @return bool whether the renewal was funded
     */
    public function renew(string $workspaceId, ?int $nowTs = null): bool
    {
        $nowTs ??= time();
        $plan = $this->plans->find($workspaceId);
        if ($plan === null) {
            return false;
        }
        $planId = (string) $plan['id'];

        // Recompute from the current catalog so price changes apply at renewal.
        $seats = (int) $plan['seats_paid'];
        $features = $this->featurePrices($this->plans->baseFeatureKeys($planId));
        $cost = $seats * $this->pricing->seatPriceCents() + array_sum($features);

        if ($cost > 0 && ! $this->wallet->canAfford($workspaceId, $cost)) {
            $this->plans->update($planId, ['status' => 'locked', 'locked_at' => $this->sql($nowTs)]);
            $this->events->dispatch('plan.lapsed', ['workspace_id' => $workspaceId, 'cost_cents' => $cost]);
            $this->events->dispatch('plan.locked', ['workspace_id' => $workspaceId, 'cost_cents' => $cost]);

            return false;
        }

        if ($cost > 0) {
            $this->wallet->debit($workspaceId, $cost, 'renewal', $planId, 'Plan renewal', null);
        }

        $periodStart = $this->sql($nowTs);
        $periodEnd = $this->monthEnd($nowTs);
        $this->plans->update($planId, [
            'status' => 'active', 'monthly_cost_cents' => $cost,
            'period_start' => $periodStart, 'period_end' => $periodEnd, 'locked_at' => null,
        ]);
        $this->issueInvoice($workspaceId, $cost, $periodStart, $periodEnd, 'Plan renewal', $seats, array_keys($features));

        $this->events->dispatch('plan.renewed', ['workspace_id' => $workspaceId, 'cost_cents' => $cost]);
        $this->maybeLowBalance($workspaceId);

        return true;
    }

    /**
     * Add one billable seat mid-term at a full month's price (no pro-rata), debited
     * now and expiring with the plan.
     */
    public function addSeat(string $workspaceId, ?string $actorUserId = null, ?int $nowTs = null): void
    {
        $nowTs ??= time();
        $plan = $this->requireActivePlan($workspaceId);
        $price = $this->pricing->seatPriceCents();

        if ($price > 0 && ! $this->wallet->canAfford($workspaceId, $price)) {
            throw new BillingException('Not enough credits for another seat. Please top up first.');
        }
        if ($price > 0) {
            $this->wallet->debit($workspaceId, $price, 'seat', (string) $plan['id'], 'Extra seat (this month)', $actorUserId);
        }
        $this->plans->addAddon($workspaceId, (string) $plan['id'], 'seat', null, $price, (string) $plan['period_end'], $actorUserId);

        $this->events->dispatch('seat.added', ['workspace_id' => $workspaceId, 'price_cents' => $price]);
        $this->maybeLowBalance($workspaceId);
    }

    /**
     * Activate an add-on feature mid-term: charged now, expires with the plan
     * (docs/WALLET_AND_BILLING.md §6).
     */
    public function addAddonFeature(string $workspaceId, string $featureKey, ?string $actorUserId = null, ?int $nowTs = null): void
    {
        $nowTs ??= time();
        $plan = $this->requireActivePlan($workspaceId);

        if (in_array($featureKey, $this->plans->activeFeatureKeys($workspaceId), true)) {
            throw new BillingException('That feature is already active in your plan.');
        }
        $price = $this->pricing->featurePriceCents($featureKey);
        if ($price <= 0) {
            throw new BillingException('That feature is not available as a paid add-on.');
        }
        if (! $this->wallet->canAfford($workspaceId, $price)) {
            throw new BillingException('Not enough credits for this add-on. Please top up first.');
        }

        $this->wallet->debit($workspaceId, $price, 'addon', (string) $plan['id'], 'Add-on: ' . $featureKey, $actorUserId);
        $this->plans->addAddon($workspaceId, (string) $plan['id'], 'feature', $featureKey, $price, (string) $plan['period_end'], $actorUserId);

        $this->events->dispatch('addon.activated', ['workspace_id' => $workspaceId, 'feature' => $featureKey, 'price_cents' => $price]);
        $this->maybeLowBalance($workspaceId);
    }

    /** @return array<string,mixed> */
    private function requireActivePlan(string $workspaceId): array
    {
        $plan = $this->plans->find($workspaceId);
        if ($plan === null) {
            throw new BillingException('Compose a plan before adding seats or add-ons.');
        }
        if ((string) $plan['status'] === 'locked') {
            throw new BillingException('Your plan is locked. Top up and renew it first.');
        }

        return $plan;
    }

    private function issueInvoice(string $workspaceId, int $cost, string $start, ?string $end, string $label, int $seats, array $featureKeys): void
    {
        if ($cost <= 0) {
            return;
        }
        $items = [['label' => $label . " — {$seats} seat(s)", 'amount_cents' => $seats * $this->pricing->seatPriceCents()]];
        foreach ($featureKeys as $key) {
            $items[] = ['label' => 'Feature: ' . (string) $key, 'amount_cents' => $this->pricing->featurePriceCents((string) $key)];
        }
        $invoiceId = $this->invoices->issue($workspaceId, null, $cost, 'USD', $start, $end, $items);
        $this->invoices->markPaid($invoiceId, 'wallet');
    }

    /** Emit a low-balance signal when the wallet can no longer fund a month. */
    private function maybeLowBalance(string $workspaceId): void
    {
        $plan = $this->plans->find($workspaceId);
        $monthly = $plan !== null ? (int) $plan['monthly_cost_cents'] : 0;
        if ($monthly > 0 && $this->wallet->balance($workspaceId) < $monthly) {
            $this->events->dispatch('wallet.low_balance', [
                'workspace_id' => $workspaceId,
                'balance_cents' => $this->wallet->balance($workspaceId),
                'monthly_cost_cents' => $monthly,
            ]);
        }
    }

    private function monthEnd(int $nowTs): string
    {
        return gmdate('Y-m-d H:i:s', strtotime('+1 month', $nowTs) ?: $nowTs);
    }

    private function sql(int $nowTs): string
    {
        return gmdate('Y-m-d H:i:s', $nowTs);
    }
}
