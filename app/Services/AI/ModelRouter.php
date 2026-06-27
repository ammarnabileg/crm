<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Core\Database;

/**
 * Model Router (docs/51 §12) — selects, for a capability, an ORDERED list of
 * provider/model candidates the AiGateway tries in turn (the fallback chain). It
 * considers only providers the tenant has active keys for (the platform stores no
 * keys), then ranks by: the tenant's default key, an explicit per-call preference,
 * the configured provider preference order, and price (cheaper first).
 *
 * `rank()` is a pure function (fully testable); `candidatesFor()` loads the live
 * tenant keys + the `ai_models` catalog.
 */
final class ModelRouter
{
    public function __construct(private readonly Database $db)
    {
    }

    public static function make(): self
    {
        return new self(app('db'));
    }

    /**
     * Build the ranked candidate list for a capability in the current tenant.
     *
     * @return array<int, array<string,mixed>> each: provider, model, model_id, key_id, ...
     */
    public function candidatesFor(string $capability, ?string $preferred = null): array
    {
        $tenantId = tenant()->id();
        if ($tenantId === null) {
            return [];
        }

        $keys = $this->db->table('tenant_ai_keys')
            ->where('workspace_id', '=', $tenantId)
            ->where('is_active', '=', 1)
            ->whereNull('deleted_at')
            ->get();

        $candidates = [];
        foreach ($keys as $key) {
            $provider = (string) $key['provider'];
            $models = $this->db->table('ai_models')
                ->where('provider_id', '=', $this->providerId($provider))
                ->where('capability', '=', $capability)
                ->where('is_active', '=', 1)
                ->whereNull('deleted_at')
                ->orderBy('is_default', 'desc')
                ->get();

            foreach ($models as $model) {
                $candidates[] = [
                    'provider'        => $provider,
                    'model'           => (string) $model['key'],
                    'model_id'        => (int) $model['id'],
                    'key_id'          => (int) $key['id'],
                    'is_default_key'  => (int) $key['is_default'] === 1,
                    'is_default_model' => (int) $model['is_default'] === 1,
                    'input_price'     => $model['input_price_per_1k'] !== null ? (float) $model['input_price_per_1k'] : null,
                ];
            }
        }

        return $this->rank($candidates, $preferred);
    }

    /**
     * Pure ranking of candidate descriptors (best first).
     *
     * @param array<int, array<string,mixed>> $candidates
     * @return array<int, array<string,mixed>>
     */
    public function rank(array $candidates, ?string $preferred = null): array
    {
        $preferenceOrder = (array) config('ai.router.preference', []);
        $indexOf = array_flip($preferenceOrder);
        $big = count($preferenceOrder) + 1;

        usort($candidates, function (array $a, array $b) use ($preferred, $indexOf, $big): int {
            // 1) Tenant default key wins.
            $ad = ($a['is_default_key'] ?? false) ? 0 : 1;
            $bd = ($b['is_default_key'] ?? false) ? 0 : 1;
            if ($ad !== $bd) {
                return $ad <=> $bd;
            }
            // 2) Explicit per-call preference.
            if ($preferred !== null) {
                $ap = ($a['provider'] ?? '') === $preferred ? 0 : 1;
                $bp = ($b['provider'] ?? '') === $preferred ? 0 : 1;
                if ($ap !== $bp) {
                    return $ap <=> $bp;
                }
            }
            // 3) Configured provider preference order.
            $ai = $indexOf[$a['provider'] ?? ''] ?? $big;
            $bi = $indexOf[$b['provider'] ?? ''] ?? $big;
            if ($ai !== $bi) {
                return $ai <=> $bi;
            }
            // 4) Cheaper input price first (unknown price ranks last).
            $ac = $a['input_price'] ?? PHP_FLOAT_MAX;
            $bc = $b['input_price'] ?? PHP_FLOAT_MAX;
            if ($ac !== $bc) {
                return $ac <=> $bc;
            }
            // 5) Default model as a final tiebreaker.
            $am = ($a['is_default_model'] ?? false) ? 0 : 1;
            $bm = ($b['is_default_model'] ?? false) ? 0 : 1;

            return $am <=> $bm;
        });

        return $candidates;
    }

    private function providerId(string $providerKey): int
    {
        return (int) $this->db->table('ai_providers')
            ->where('key', '=', $providerKey)
            ->value('id');
    }
}
