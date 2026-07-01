<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\ValueObject;

use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Support\Assert;

/**
 * An immutable running total of the token and monetary cost an execution has accrued.
 *
 * As each step completes, its {@see WorkerResult} cost is folded into the execution's snapshot via
 * {@see self::add()}, which returns a new snapshot (the type is immutable). The per-provider
 * breakdown maps a provider name to its accrued currency micros so cost can be attributed. All
 * amounts are non-negative and expressed in currency micros (millionths of a currency unit) to avoid
 * floating-point money.
 */
final class CostSnapshot implements ValueObject
{
    /** @var array<string, int> */
    private readonly array $providerBreakdown;

    /**
     * @param int                $tokens            The total tokens consumed (>= 0).
     * @param int                $currencyMicros    The total monetary cost in currency micros (>= 0).
     * @param array<string, int> $providerBreakdown Per-provider currency-micros breakdown.
     */
    public function __construct(
        private readonly int $tokens,
        private readonly int $currencyMicros,
        array $providerBreakdown = [],
    ) {
        Assert::that($tokens >= 0, 'Token count must not be negative.');
        Assert::that($currencyMicros >= 0, 'Cost in currency micros must not be negative.');

        $normalized = [];
        foreach ($providerBreakdown as $provider => $micros) {
            Assert::that(is_string($provider) && $provider !== '', 'Provider names must be non-empty strings.');
            Assert::that(is_int($micros) && $micros >= 0, 'Provider cost must be a non-negative integer.');
            $normalized[$provider] = $micros;
        }
        ksort($normalized);
        $this->providerBreakdown = $normalized;
    }

    /**
     * The zero snapshot: no tokens, no cost, no providers.
     */
    public static function zero(): self
    {
        return new self(0, 0, []);
    }

    /**
     * Fold another cost into this one, returning a new combined snapshot.
     *
     * Tokens and total micros sum; per-provider micros accumulate by provider name.
     *
     * @param int                $tokens            Tokens to add (>= 0).
     * @param int                $currencyMicros    Currency micros to add (>= 0).
     * @param array<string, int> $providerBreakdown Per-provider micros to accumulate.
     */
    public function add(int $tokens, int $currencyMicros, array $providerBreakdown = []): self
    {
        $merged = $this->providerBreakdown;
        foreach ($providerBreakdown as $provider => $micros) {
            Assert::that(is_string($provider) && $provider !== '', 'Provider names must be non-empty strings.');
            Assert::that(is_int($micros) && $micros >= 0, 'Provider cost must be a non-negative integer.');
            $merged[$provider] = ($merged[$provider] ?? 0) + $micros;
        }

        return new self(
            $this->tokens + $tokens,
            $this->currencyMicros + $currencyMicros,
            $merged,
        );
    }

    /**
     * The total tokens consumed.
     */
    public function tokens(): int
    {
        return $this->tokens;
    }

    /**
     * The total monetary cost in currency micros.
     */
    public function currencyMicros(): int
    {
        return $this->currencyMicros;
    }

    /**
     * The per-provider currency-micros breakdown, ordered by provider name.
     *
     * @return array<string, int>
     */
    public function providerBreakdown(): array
    {
        return $this->providerBreakdown;
    }

    /**
     * Structural equality across every attribute.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $other->tokens === $this->tokens
            && $other->currencyMicros === $this->currencyMicros
            && $other->providerBreakdown === $this->providerBreakdown;
    }

    /**
     * A scalar-only representation suitable for JSON persistence and read models.
     *
     * @return array{tokens: int, currencyMicros: int, providerBreakdown: array<string, int>}
     */
    public function toArray(): array
    {
        return [
            'tokens' => $this->tokens,
            'currencyMicros' => $this->currencyMicros,
            'providerBreakdown' => $this->providerBreakdown,
        ];
    }
}
