<?php

declare(strict_types=1);

namespace HaHireAI\Modules\AiEngine\Application;

use HaHireAI\Modules\AiEngine\Contracts\AiCapabilities;

/**
 * Resolves AI feature availability from the workspace's own keys (AiCapabilities
 * contract). Keys are per-workspace and isolated, so availability is per-workspace.
 */
final class AiCapabilityService implements AiCapabilities
{
    public function __construct(private readonly AiSettingsService $settings)
    {
    }

    public function interviewsEnabled(string $workspaceId): bool
    {
        return $this->settings->hasKey($workspaceId, 'openai');
    }

    public function cvAnalysisEnabled(string $workspaceId): bool
    {
        return $this->settings->hasKey($workspaceId, 'openai');
    }

    public function videoEnabled(string $workspaceId): bool
    {
        // The avatar (HeyGen) speaks the AI's (OpenAI) questions — both are needed.
        return $this->settings->hasKey($workspaceId, 'heygen') && $this->settings->hasKey($workspaceId, 'openai');
    }

    public function status(string $workspaceId): array
    {
        $openai = $this->settings->hasKey($workspaceId, 'openai');
        $heygen = $this->settings->hasKey($workspaceId, 'heygen');

        return [
            'openai' => $openai,
            'heygen' => $heygen,
            'interviews' => $openai,
            'cv_analysis' => $openai,
            'video' => $openai && $heygen,
        ];
    }
}
