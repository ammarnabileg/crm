<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Integration\Infrastructure\Social;

/**
 * Small builder for the normalised social snapshot envelope, so every adapter
 * produces the exact same shape (see {@see \HaHireAI\Core\Contracts\SocialProfileProbe}).
 * Keeps adapters terse and consistent. Pure helper.
 */
final class SnapshotEnvelope
{
    /** @var list<array{key:string,string_value:?string,numeric_value:?int}> */
    private array $signals = [];

    /** @var list<string> */
    private array $skills = [];

    private array $textParts = [];

    public function __construct(
        private readonly string $platform,
        private bool $reachable = false,
        private bool $fetched = false,
        private ?int $footprint = null,
        private ?string $summary = null,
        private ?string $error = null,
    ) {
    }

    public function reachable(bool $v = true): self
    {
        $this->reachable = $v;

        return $this;
    }

    public function fetched(bool $v = true): self
    {
        $this->fetched = $v;

        return $this;
    }

    public function footprint(?int $v): self
    {
        $this->footprint = $v === null ? null : max(0, min(100, $v));

        return $this;
    }

    public function summary(?string $v): self
    {
        $this->summary = $v !== null ? mb_substr($v, 0, 480) : null;

        return $this;
    }

    public function error(?string $v): self
    {
        $this->error = $v !== null ? mb_substr($v, 0, 240) : null;

        return $this;
    }

    public function text(?string $v): self
    {
        if ($v !== null && trim($v) !== '') {
            $this->textParts[] = trim($v);
        }

        return $this;
    }

    /** @param list<string> $skills */
    public function addSkills(array $skills): self
    {
        foreach ($skills as $s) {
            $s = trim((string) $s);
            if ($s !== '' && mb_strlen($s) <= 40) {
                $this->skills[mb_strtolower($s)] = $s;
            }
        }

        return $this;
    }

    public function signal(string $key, ?string $stringValue = null, ?int $numericValue = null): self
    {
        $this->signals[] = ['key' => $key, 'string_value' => $stringValue, 'numeric_value' => $numericValue];

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'platform' => $this->platform,
            'reachable' => $this->reachable,
            'fetched' => $this->fetched,
            'footprint_strength' => $this->footprint,
            'relevance_text' => mb_substr(implode(' ', $this->textParts), 0, 4000),
            'skills' => array_values($this->skills),
            'signals' => $this->signals,
            'summary' => $this->summary,
            'error' => $this->error,
        ];
    }

    /** A neutral, unreachable snapshot (contributes nothing to the score). */
    public static function unreachable(string $platform, ?string $error = null): array
    {
        return (new self($platform))->error($error)->toArray();
    }

    /** Log-scaled magnitude helper: maps a count to 0..$max with diminishing returns. */
    public static function logScale(int $value, int $pivot, int $max = 100): int
    {
        if ($value <= 0) {
            return 0;
        }

        return (int) max(0, min($max, round($max * (log10($value + 1) / log10($pivot + 1)))));
    }
}
