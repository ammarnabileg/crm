<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * One observed anti-cheating signal for an interview (docs/51 §16, P8).
 *
 * CONFIDENCE ONLY, NEVER PROOF. A signal is an ADVISORY observation (a tab switch,
 * a paste, a second face) — it is never evidence of misconduct on its own. Signals
 * are append-only and immutable once recorded (no updated_at), and are aggregated
 * by App\Services\AntiCheat\CheatingDetector into a CheatingScore confidence band.
 * Tenant-scoped.
 */
final class CheatingSignal extends Model
{
    protected static string $table = 'cheating_signals';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $timestamps = false;

    protected static array $fillable = [
        'interview_id', 'signal_type', 'weight', 'value', 'observed_at', 'meta',
    ];

    protected static array $casts = [
        'weight' => 'float',
        'value'  => 'float',
        'meta'   => 'array',
    ];
}
