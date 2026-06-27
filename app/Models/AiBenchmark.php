<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * A Model-Benchmark suite (docs/51 §19): a named, tenant-owned scenario the
 * workspace runs across several provider/model candidates to compare engines on
 * its OWN data (accuracy, latency, cost, quality/reasoning/language). The
 * `scenario` is the prompt text; `config` holds expectations/weights. Each run
 * produces `ai_benchmark_results` rows. Tenant-scoped, uuid + timestamps.
 */
final class AiBenchmark extends Model
{
    protected static string $table = 'ai_benchmarks';
    protected static bool $tenantScoped = true;
    protected static bool $usesUuid = true;

    protected static array $fillable = [
        'name', 'slug', 'scenario', 'config', 'created_by',
    ];

    protected static array $casts = [
        'config' => 'array',
    ];

    /** @return array<int, array<string,mixed>> This benchmark's recorded results. */
    public function results(): array
    {
        return self::db()->table('ai_benchmark_results')
            ->where('benchmark_id', '=', (int) $this->getKey())
            ->orderBy('id')
            ->get();
    }
}
