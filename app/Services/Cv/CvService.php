<?php

declare(strict_types=1);

namespace App\Services\Cv;

use App\Core\Database;
use App\Core\Model;

/**
 * Orchestrates "read the applicant's CV": extract text → parse to structured data →
 * persist. The persisted result powers the recruiter's view and makes the candidate
 * searchable, without ever blocking the thing that triggered it (an application).
 *
 * Storage strategy:
 *  - a `cv_parses` row keeps the FULL structured payload (source-tracked, re-viewable);
 *  - the candidate's global `candidate_profiles` row is enriched with the safe scalar
 *    fields (headline/summary/current title/years/city) — only filling blanks, never
 *    clobbering a profile the candidate curated;
 *  - parsed skills become `skills` + `candidate_skills` in the parsing workspace, so
 *    the existing talent search can find the person by skill.
 */
final class CvService
{
    private Database $db;

    public function __construct(
        private readonly CvExtractor $extractor = new CvExtractor(),
        private readonly ?CvParser $parser = null,
        ?Database $db = null,
    ) {
        $this->db = $db ?? app('db');
    }

    /**
     * Read a stored CV file for a candidate and persist the structured result.
     * Never throws on a bad file — returns the (possibly empty) parse so the caller
     * (e.g. a public application) always completes.
     *
     * @param array<string,mixed> $aiContext Optional routing hints for tests.
     * @return array{parse:array<string,mixed>, confident:bool, stored:bool, cv_parse_id:?int}
     */
    public function parseFile(int $userId, string $filePath, string $originalName, ?int $workspaceId = null, ?int $fileId = null, array $aiContext = []): array
    {
        try {
            $extracted = $this->extractor->extract($filePath, $originalName);
            $parser = $this->parser ?? new CvParser();
            $parse = $parser->parse($extracted['text'], $workspaceId, $aiContext);

            $cvParseId = $this->store($userId, $workspaceId, $fileId, $parse, (bool) $extracted['confident']);
            $this->enrichProfile($userId, $parse);
            if ($workspaceId !== null) {
                $this->storeSkills($userId, $workspaceId, (array) $parse['skills']);
            }

            return ['parse' => $parse, 'confident' => (bool) $extracted['confident'], 'stored' => true, 'cv_parse_id' => $cvParseId];
        } catch (\Throwable $e) {
            // CV reading is best-effort enrichment — never let it break the caller.
            return ['parse' => [], 'confident' => false, 'stored' => false, 'cv_parse_id' => null];
        }
    }

    /**
     * The most recent parse for a candidate, decoded for display.
     *
     * @return array<string,mixed>|null
     */
    public function latestForUser(int $userId): ?array
    {
        $row = $this->db->table('cv_parses')
            ->where('user_id', '=', $userId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($row === null) {
            return null;
        }

        $data = $row['data'] !== null ? json_decode((string) $row['data'], true) : null;

        return [
            'source'    => (string) $row['source'],
            'confident' => (bool) $row['confident'],
            'summary'   => $row['summary'] !== null ? (string) $row['summary'] : null,
            'data'      => is_array($data) ? $data : [],
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $parse
     */
    private function store(int $userId, ?int $workspaceId, ?int $fileId, array $parse, bool $confident): int
    {
        return (int) $this->db->table('cv_parses')->insertGetId([
            'uuid'         => Model::generateUuid(),
            'user_id'      => $userId,
            'workspace_id' => $workspaceId,
            'file_id'      => $fileId,
            'source'       => (string) ($parse['source'] ?? 'none'),
            'confident'    => $confident ? 1 : 0,
            'summary'      => $parse['summary'] ?? null,
            'data'         => json_encode($parse, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    /**
     * Fill the candidate's global profile scalar fields from the parse — only where
     * the profile is currently empty, so a curated profile is never overwritten.
     *
     * @param array<string,mixed> $parse
     */
    private function enrichProfile(int $userId, array $parse): void
    {
        $existing = $this->db->table('candidate_profiles')->where('user_id', '=', $userId)->first();

        $candidate = [
            'headline'               => $parse['headline'] ?? ($parse['current_title'] ?? null),
            'summary'                => $parse['summary'] ?? null,
            'current_title'          => $parse['current_title'] ?? null,
            'total_experience_years' => $parse['total_experience_years'] ?? null,
            'city'                   => $parse['city'] ?? null,
        ];

        if ($existing === null) {
            $this->db->table('candidate_profiles')->insert(array_merge([
                'uuid'       => Model::generateUuid(),
                'user_id'    => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ], array_filter($candidate, static fn ($v): bool => $v !== null && $v !== '')));

            return;
        }

        // Only fill blanks on an existing profile.
        $update = [];
        foreach ($candidate as $col => $val) {
            if (($val !== null && $val !== '') && ($existing[$col] === null || $existing[$col] === '' || (float) ($existing[$col] ?? 0) === 0.0 && $col === 'total_experience_years')) {
                $update[$col] = $val;
            }
        }
        if ($update !== []) {
            $update['updated_at'] = now();
            $this->db->table('candidate_profiles')->where('user_id', '=', $userId)->update($update);
        }
    }

    /**
     * Find-or-create each skill in the workspace and link it to the candidate, so the
     * existing talent search can match on it. Idempotent (PK on user_id+skill_id).
     *
     * @param string[] $skills
     */
    private function storeSkills(int $userId, int $workspaceId, array $skills): void
    {
        foreach (array_slice($skills, 0, 30) as $name) {
            $name = trim((string) $name);
            if ($name === '' || mb_strlen($name) > 120) {
                continue;
            }

            $skillId = (int) ($this->db->table('skills')
                ->where('workspace_id', '=', $workspaceId)
                ->whereRaw('LOWER(`name`) = ?', [mb_strtolower($name)])
                ->whereNull('deleted_at')
                ->value('id') ?? 0);

            if ($skillId === 0) {
                $skillId = (int) $this->db->table('skills')->insertGetId([
                    'uuid'         => Model::generateUuid(),
                    'workspace_id' => $workspaceId,
                    'name'         => $name,
                    'slug'         => slugify($name) . '-' . substr(bin2hex(random_bytes(3)), 0, 6),
                    'is_active'    => 1,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            $linked = $this->db->table('candidate_skills')
                ->where('user_id', '=', $userId)
                ->where('skill_id', '=', $skillId)
                ->exists();
            if (! $linked) {
                $this->db->table('candidate_skills')->insert([
                    'user_id'    => $userId,
                    'skill_id'   => $skillId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
