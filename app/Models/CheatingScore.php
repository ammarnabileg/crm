<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * The aggregated anti-cheating CONFIDENCE for an interview (docs/51 §16, P8).
 *
 * CONFIDENCE ONLY, NEVER PROOF. `confidence` is a normalized 0–100 ADVISORY score
 * and `level` is a low/medium/high CONFIDENCE BAND — NOT a boolean "cheated", NOT a
 * verdict, NOT an accusation. There is deliberately no "cheated" / "passed" column:
 * a human reviewer interprets the score in context. `breakdown` explains the
 * per-signal-type contribution (explainable AI). One row per interview
 * (UNIQUE interview_id), upserted as signals accumulate. Tenant-scoped.
 */
final class CheatingScore extends Model
{
    protected static string $table = 'cheating_scores';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'interview_id', 'confidence', 'level', 'signals_count', 'breakdown', 'computed_at',
    ];

    protected static array $casts = [
        'confidence'    => 'float',
        'signals_count' => 'int',
        'breakdown'     => 'array',
    ];
}
