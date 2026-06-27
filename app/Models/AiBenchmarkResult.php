<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * One measurement row of a Model Benchmark (docs/51 §19): the outcome of running
 * a benchmark scenario through a single provider/model. Captures accuracy,
 * latency, cost and the per-dimension quality/reasoning/language scores so the
 * BenchmarkEngine can recommend the best engine for a capability. Append-only
 * (no `updated_at`); tenant-scoped, uuid.
 */
final class AiBenchmarkResult extends Model
{
    protected static string $table = 'ai_benchmark_results';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;
    protected static bool $timestamps = false;

    protected static array $fillable = [
        'benchmark_id', 'provider', 'model_key', 'accuracy', 'latency_ms',
        'cost', 'quality_score', 'reasoning_score', 'language_score', 'raw',
    ];

    protected static array $casts = [
        'accuracy'        => 'float',
        'latency_ms'      => 'int',
        'cost'            => 'float',
        'quality_score'   => 'float',
        'reasoning_score' => 'float',
        'language_score'  => 'float',
        'raw'             => 'array',
    ];
}
