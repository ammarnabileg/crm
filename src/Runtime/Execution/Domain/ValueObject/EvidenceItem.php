<?php

declare(strict_types=1);

namespace Nizam\Runtime\Execution\Domain\ValueObject;

use DateTimeImmutable;
use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Support\Assert;

/**
 * An immutable pointer to one piece of evidence a worker relied on or produced.
 *
 * Every {@see WorkerResult} carries a list of these so the manager's decision — and any later audit
 * or replay — can be traced back to concrete artefacts: a record looked up, a document read, a tool
 * output. Each item names its {@see self::kind()} (a free-form category such as "record" or
 * "document"), a stable {@see self::reference()} into the originating system, a short human
 * {@see self::summary()}, and when it was captured. Being a value object, two items with the same
 * attributes are interchangeable, which lets the result merger deduplicate evidence across workers.
 */
final class EvidenceItem implements ValueObject
{
    /**
     * @param string            $kind       The category of evidence (e.g. "record", "document", "tool_output").
     * @param string            $reference  A stable id/URI into the source system.
     * @param string            $summary    A short human-readable description of the evidence.
     * @param DateTimeImmutable $capturedAt When the evidence was captured.
     */
    public function __construct(
        private readonly string $kind,
        private readonly string $reference,
        private readonly string $summary,
        private readonly DateTimeImmutable $capturedAt,
    ) {
        Assert::notEmpty($kind, 'Evidence kind must not be empty.');
        Assert::notEmpty($reference, 'Evidence reference must not be empty.');
        Assert::notEmpty($summary, 'Evidence summary must not be empty.');
    }

    /**
     * The category of evidence.
     */
    public function kind(): string
    {
        return $this->kind;
    }

    /**
     * The stable id/URI into the source system.
     */
    public function reference(): string
    {
        return $this->reference;
    }

    /**
     * The short human-readable description of the evidence.
     */
    public function summary(): string
    {
        return $this->summary;
    }

    /**
     * When the evidence was captured.
     */
    public function capturedAt(): DateTimeImmutable
    {
        return $this->capturedAt;
    }

    /**
     * A stable de-duplication key: the kind and reference identify the same artefact.
     */
    public function dedupeKey(): string
    {
        return $this->kind . "\0" . $this->reference;
    }

    /**
     * Structural equality across every attribute.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $other->kind === $this->kind
            && $other->reference === $this->reference
            && $other->summary === $this->summary
            && $other->capturedAt->getTimestamp() === $this->capturedAt->getTimestamp();
    }

    /**
     * A scalar-only representation suitable for JSON persistence and read models.
     *
     * @return array{kind: string, reference: string, summary: string, capturedAt: string}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'reference' => $this->reference,
            'summary' => $this->summary,
            'capturedAt' => $this->capturedAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
