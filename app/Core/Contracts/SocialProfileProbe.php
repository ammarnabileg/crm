<?php

declare(strict_types=1);

namespace HaHireAI\Core\Contracts;

/**
 * The single, provider-agnostic door through which the Recruitment module asks
 * the Integration Platform to collect PUBLIC information about a candidate from
 * their social links. Recruitment depends ONLY on this contract — it never knows
 * which sources exist or how they are fetched (ARCHITECTURE.md §4, §8: all
 * external connectivity lives behind the Integration Platform).
 *
 * New sources (LinkedIn, GitHub, Kaggle, Behance, Google Scholar, StackOverflow,
 * Medium, …) are added as Integration adapters with NO change to Recruitment or
 * to the scoring engine.
 *
 * Each returned snapshot is a normalised, plain-array envelope so no value object
 * is shared across module boundaries:
 *
 *   [
 *     'platform'           => string,            // 'github' | 'website' | …
 *     'url'                => string,
 *     'reachable'          => bool,              // did we reach it at all?
 *     'fetched'            => bool,              // did we read structured data?
 *     'footprint_strength' => ?int,             // 0..100 magnitude of public activity (null = none)
 *     'relevance_text'     => string,           // public text (bio/repos/headline) for job matching
 *     'skills'             => list<string>,      // skills the adapter could infer (canonical)
 *     'signals'            => list<array{key:string,string_value?:?string,numeric_value?:?int}>,
 *     'summary'            => ?string,
 *     'error'              => ?string,
 *   ]
 *
 * Implementations MUST be resilient: a blocked, missing, slow, or offline source
 * yields reachable=false with no signals — NEVER an exception — so the gate
 * degrades gracefully and a candidate is never penalised for an unreachable link.
 */
interface SocialProfileProbe
{
    /**
     * @param  list<string>  $urls
     * @return list<array<string, mixed>>  one snapshot envelope per url
     */
    public function probe(array $urls): array;
}
