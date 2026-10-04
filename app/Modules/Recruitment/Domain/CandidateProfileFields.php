<?php

declare(strict_types=1);

namespace HaHireAI\Modules\Recruitment\Domain;

/**
 * Flattens the legacy `candidate_profiles.details` JSON map into normalised
 * key/value rows for the new `candidate_profile_fields` table. Pure and shared by
 * both the dual-write path (CandidateProfileService) and the backfill migration,
 * so the two can never drift.
 *
 * A scalar field (email, availability, current_salary…) becomes one row; an array
 * field (skills, education, languages…) becomes one row per value. This is the
 * gradual, backward-compatible migration off the JSON blob (docs/DATABASE_ARCHITECTURE.md).
 */
final class CandidateProfileFields
{
    private const MAX_ROWS_PER_KEY = 60;

    /**
     * @param  array<string, mixed>  $details
     * @return list<array{key: string, value: string, position: int}>
     */
    public static function flatten(array $details): array
    {
        $rows = [];
        foreach ($details as $key => $value) {
            $key = mb_substr(trim((string) $key), 0, 64);
            if ($key === '') {
                continue;
            }
            if (is_array($value)) {
                $pos = 0;
                foreach ($value as $item) {
                    if (is_array($item)) {
                        continue; // only flat scalar members are indexed
                    }
                    $v = self::clean($item);
                    if ($v !== '') {
                        $rows[] = ['key' => $key, 'value' => $v, 'position' => $pos++];
                    }
                    if ($pos >= self::MAX_ROWS_PER_KEY) {
                        break;
                    }
                }

                continue;
            }
            $v = self::clean($value);
            if ($v !== '') {
                $rows[] = ['key' => $key, 'value' => $v, 'position' => 0];
            }
        }

        return $rows;
    }

    private static function clean(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (! is_scalar($value)) {
            return '';
        }

        return mb_substr(trim((string) $value), 0, 500);
    }
}
