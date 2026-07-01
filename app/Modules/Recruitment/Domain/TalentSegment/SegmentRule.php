<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Domain\TalentSegment;

use InvalidArgumentException;

/**
 * One immutable criterion of a Smart Segment (e.g. "Skill LIKE React",
 * "Score >= 85", "Available"). Validated against {@see SegmentField} on
 * construction so a malformed rule can never reach the database or the compiler.
 */
final class SegmentRule
{
    public function __construct(
        public readonly string $field,
        public readonly string $value,
        public readonly int $position = 0,
    ) {
        if (! SegmentField::isValid($field)) {
            throw new InvalidArgumentException("Unknown segment field: {$field}");
        }
        if (SegmentField::requiresValue($field) && trim($value) === '') {
            throw new InvalidArgumentException("Segment field '{$field}' requires a value.");
        }
    }

    /** The canonical operator for this rule's field. */
    public function operator(): string
    {
        return SegmentField::operator($this->field);
    }

    /**
     * Rehydrate from a persisted row (defensive casts; unknown fields throw).
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (string) ($row['field'] ?? ''),
            (string) ($row['value'] ?? ''),
            (int) ($row['position'] ?? 0),
        );
    }
}
