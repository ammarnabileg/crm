<?php

declare(strict_types=1);

namespace Nizam\Behavior\Domain\ValueObject;

use DateTimeImmutable;
use Nizam\Behavior\Domain\Enum\BehaviorTraitAxis;
use Nizam\Behavior\Domain\Enum\ObservationSourceType;
use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Support\Assert;

/**
 * An immutable pointer to one piece of approved business practice that justifies behavior.
 *
 * Every recommendation, revision, and observation cites its evidence through this value object: a
 * source type, a stable reference id into the originating system, a short human summary, when the
 * practice occurred, a normalized weight (0..1) expressing how strongly it should count in
 * consolidation, and — the actual behavioral signal — the style-axis values the practice attests to.
 *
 * The `observedTraits` map is what a piece of approved practice SAYS about how the role behaves: for
 * each behavior style axis it touches, the enum value it demonstrates (e.g. an approved decision that
 * was reached by consulting stakeholders attests `decisionStyle => consultative`). It may be empty
 * (some evidence corroborates the role's discipline without asserting any particular axis), and it
 * carries at most one value per axis. This is the per-trait signal the consolidator votes on to
 * derive a role's consolidated behavior; without it, learning HOW work is performed is impossible.
 * Being a value object, two references with the same attributes are interchangeable.
 */
final class EvidenceReference implements ValueObject
{
    /**
     * @var array<string, string> Observed style-axis values, keyed by {@see BehaviorTraitAxis} value.
     */
    private readonly array $observedTraits;

    /**
     * @param ObservationSourceType $sourceType     The kind of approved practice this cites.
     * @param string                $referenceId    Stable id of the source record in its origin system.
     * @param string                $summary        Short human-readable description of the evidence.
     * @param DateTimeImmutable     $occurredAt     When the cited practice occurred.
     * @param float                 $weight         Normalized influence in [0, 1].
     * @param array<string, string> $observedTraits Style-axis values this practice attests to, keyed by
     *                                              {@see BehaviorTraitAxis} value; each value a case of
     *                                              that axis's enum. May be empty.
     */
    public function __construct(
        private readonly ObservationSourceType $sourceType,
        private readonly string $referenceId,
        private readonly string $summary,
        private readonly DateTimeImmutable $occurredAt,
        private readonly float $weight,
        array $observedTraits = [],
    ) {
        Assert::notEmpty($referenceId, 'Evidence referenceId must not be empty.');
        Assert::notEmpty($summary, 'Evidence summary must not be empty.');
        Assert::that(
            $weight >= 0.0 && $weight <= 1.0,
            'Evidence weight must be within the inclusive range [0, 1].',
        );

        $this->observedTraits = $this->normalizeObservedTraits($observedTraits);
    }

    /**
     * Validate and canonicalize the observed-trait map: every key a known axis, every value a case of
     * that axis's enum, ordered by the axis registry for deterministic persistence and equality.
     *
     * @param array<array-key, mixed> $observedTraits
     *
     * @return array<string, string>
     */
    private function normalizeObservedTraits(array $observedTraits): array
    {
        $normalized = [];
        foreach (BehaviorTraitAxis::cases() as $axis) {
            if (!array_key_exists($axis->value, $observedTraits)) {
                continue;
            }

            $value = $observedTraits[$axis->value];
            Assert::that(
                is_string($value) && $axis->accepts($value),
                sprintf('Observed value for axis "%s" must be a valid case of that axis.', $axis->value),
            );

            $normalized[$axis->value] = $value;
        }

        $unknown = array_diff(array_keys($observedTraits), array_keys($normalized));
        Assert::that(
            $unknown === [],
            'Observed traits may only reference known behavior style axes.',
        );

        return $normalized;
    }

    /**
     * The kind of approved practice this evidence cites.
     */
    public function sourceType(): ObservationSourceType
    {
        return $this->sourceType;
    }

    /**
     * The stable id of the source record in its originating system.
     */
    public function referenceId(): string
    {
        return $this->referenceId;
    }

    /**
     * The short human-readable summary of the evidence.
     */
    public function summary(): string
    {
        return $this->summary;
    }

    /**
     * When the cited practice occurred.
     */
    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * The normalized influence weight in [0, 1].
     */
    public function weight(): float
    {
        return $this->weight;
    }

    /**
     * The style-axis values this practice attests to, keyed by {@see BehaviorTraitAxis} value.
     *
     * The per-trait signal the consolidator votes on. Empty when the evidence corroborates the role
     * without asserting any particular axis.
     *
     * @return array<string, string>
     */
    public function observedTraits(): array
    {
        return $this->observedTraits;
    }

    /**
     * The value this practice attests for one style axis, or null when it attests nothing for it.
     */
    public function observedValueFor(BehaviorTraitAxis $axis): ?string
    {
        return $this->observedTraits[$axis->value] ?? null;
    }

    /**
     * Structural equality across every attribute, including the observed-trait signal.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $other->sourceType === $this->sourceType
            && $other->referenceId === $this->referenceId
            && $other->summary === $this->summary
            && $other->occurredAt->getTimestamp() === $this->occurredAt->getTimestamp()
            && $other->weight === $this->weight
            && $other->observedTraits === $this->observedTraits;
    }

    /**
     * A scalar-only representation suitable for JSON persistence and read models.
     *
     * @return array{
     *     sourceType: string,
     *     referenceId: string,
     *     summary: string,
     *     occurredAt: string,
     *     weight: float,
     *     observedTraits: array<string, string>
     * }
     */
    public function toArray(): array
    {
        return [
            'sourceType' => $this->sourceType->value,
            'referenceId' => $this->referenceId,
            'summary' => $this->summary,
            'occurredAt' => $this->occurredAt->format(DateTimeImmutable::ATOM),
            'weight' => $this->weight,
            'observedTraits' => $this->observedTraits,
        ];
    }
}
