<?php

declare(strict_types=1);

namespace App\Services\Observability;

use App\Core\Database;
use App\Services\AI\ModelRouter;
use App\Services\AI\TokenOptimizer;

/**
 * Cost Optimizer (docs/51 §17) — the "spend before you call" half of the cost
 * controls. It estimates the token footprint of a prompt and the price of
 * running it on a given model, then recommends the CHEAPEST candidate the tenant
 * can actually use for a capability (via the same {@see ModelRouter} the gateway
 * routes with).
 *
 * Prices come from the `ai_models` per-1k input/output columns, which are mostly
 * NULL in seed data; a NULL price is treated as 0 ("unknown / free") so an
 * estimate — and a recommendation — is always available rather than failing.
 */
final class CostOptimizer
{
    /** Default assumption for a reply's length when the caller gives none. */
    private const DEFAULT_COMPLETION_TOKENS = 256;

    public function __construct(
        private readonly Database $db,
        private readonly TokenOptimizer $optimizer,
        private readonly ModelRouter $router,
    ) {
    }

    public static function make(): self
    {
        return new self(app('db'), new TokenOptimizer(), ModelRouter::make());
    }

    /** Rough token estimate for a piece of text (reuses the TokenOptimizer). */
    public function estimateTokens(string $text): int
    {
        return $this->optimizer->estimateTokens($text);
    }

    /**
     * Estimate the dollar (or model-currency) cost of one call to a model.
     *
     * Looks up the model's per-1k input/output prices by its catalog key; a NULL
     * price contributes 0. Returns a non-negative float.
     */
    public function estimateCost(string $modelKey, int $promptTokens, int $expectedCompletionTokens = self::DEFAULT_COMPLETION_TOKENS): float
    {
        $prices = $this->pricesForModelKey($modelKey);

        return $this->priceTokens(
            max(0, $promptTokens),
            max(0, $expectedCompletionTokens),
            $prices['input'],
            $prices['output']
        );
    }

    /**
     * The cheapest provider/model the current tenant can use for a capability.
     *
     * Ranks the live {@see ModelRouter} candidates by estimated cost for a small
     * reference prompt; ties (e.g. all-NULL seed prices) keep the router's own
     * order so a sensible default still wins. Returns the candidate descriptor
     * enriched with `estimated_cost`, or NULL when the tenant has no usable key.
     *
     * @return array<string,mixed>|null
     */
    public function cheapest(string $capability): ?array
    {
        $candidates = $this->router->candidatesFor($capability);
        if ($candidates === []) {
            return null;
        }

        // A small, representative prompt so price differences surface.
        $referenceTokens = $this->estimateTokens(str_repeat('token ', 64));

        $best = null;
        $bestCost = null;
        foreach ($candidates as $candidate) {
            $modelKey = (string) ($candidate['model'] ?? '');
            $cost = $this->estimateCost($modelKey, $referenceTokens);
            $candidate['estimated_cost'] = $cost;

            if ($bestCost === null || $cost < $bestCost) {
                $best = $candidate;
                $bestCost = $cost;
            }
        }

        return $best;
    }

    /**
     * Resolve a model's per-1k prices by its catalog key. Unknown model or NULL
     * column → 0.0 for that side.
     *
     * @return array{input:float, output:float}
     */
    private function pricesForModelKey(string $modelKey): array
    {
        if ($modelKey === '') {
            return ['input' => 0.0, 'output' => 0.0];
        }

        $row = $this->db->table('ai_models')
            ->where('key', '=', $modelKey)
            ->whereNull('deleted_at')
            ->orderBy('is_active', 'desc')
            ->first();

        if ($row === null) {
            return ['input' => 0.0, 'output' => 0.0];
        }

        return [
            'input'  => $row['input_price_per_1k'] !== null ? (float) $row['input_price_per_1k'] : 0.0,
            'output' => $row['output_price_per_1k'] !== null ? (float) $row['output_price_per_1k'] : 0.0,
        ];
    }

    private function priceTokens(int $promptTokens, int $completionTokens, float $inputPrice, float $outputPrice): float
    {
        $cost = ($promptTokens / 1000) * $inputPrice
            + ($completionTokens / 1000) * $outputPrice;

        return round(max(0.0, $cost), 6);
    }
}
