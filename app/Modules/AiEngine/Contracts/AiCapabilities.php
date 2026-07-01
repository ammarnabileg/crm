<?php

declare(strict_types=1);

namespace HaHireAI\Modules\AiEngine\Contracts;

/**
 * Answers which AI-dependent features are *actually usable* for a workspace, based
 * on the workspace's own provider keys. AI interviews and AI CV analysis need an
 * OpenAI key; the live video avatar additionally needs a HeyGen key. Without the
 * keys these features stay off — the rest of the ATS works regardless. Other
 * modules depend on this contract, never on AiSettingsService internals (§4).
 */
interface AiCapabilities
{
    /** AI (text/voice) interviews — requires an OpenAI key. */
    public function interviewsEnabled(string $workspaceId): bool;

    /** AI CV analysis / candidate assessment — requires an OpenAI key. */
    public function cvAnalysisEnabled(string $workspaceId): bool;

    /** Live video avatar interviews — requires a HeyGen key (and the AI brain). */
    public function videoEnabled(string $workspaceId): bool;

    /**
     * A compact status map for the UI.
     *
     * @return array{openai: bool, heygen: bool, interviews: bool, cv_analysis: bool, video: bool}
     */
    public function status(string $workspaceId): array;
}
