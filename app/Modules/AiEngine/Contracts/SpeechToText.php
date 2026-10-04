<?php

declare(strict_types=1);

namespace HaHireAI\Modules\AiEngine\Contracts;

/**
 * Server-side speech-to-text. Transcribes an audio clip using the *workspace's
 * own* provider key (keys are per-workspace and isolated — see AiSettingsService).
 * Returns the transcript, or null when no key is configured / the call fails, so
 * callers can gracefully fall back to the browser's on-device recognition.
 */
interface SpeechToText
{
    public function transcribe(string $workspaceId, string $audioPath, string $filename, string $mime = 'audio/webm'): ?string;
}
