<?php

declare(strict_types=1);

namespace HaHireAI\Modules\AiEngine\Infrastructure;

use Closure;
use CURLFile;
use HaHireAI\Modules\AiEngine\Application\AiSettingsService;
use HaHireAI\Modules\AiEngine\Contracts\SpeechToText;

/**
 * OpenAI Whisper speech-to-text. Uses the workspace's own OpenAI key (the same
 * key the workspace admin adds for the AI provider) — keys never cross workspace
 * boundaries. The network call is behind an injectable transport so the parsing
 * and key logic are unit-testable and a missing key/offline call degrades to null.
 */
final class OpenAiSpeechToText implements SpeechToText
{
    private const ENDPOINT = 'https://api.openai.com/v1/audio/transcriptions';
    private const MODEL = 'whisper-1';

    /** @var Closure(string, string, string, string): ?string */
    private Closure $transport;

    public function __construct(
        private readonly AiSettingsService $settings,
        ?callable $transport = null,
    ) {
        $this->transport = $transport !== null
            ? Closure::fromCallable($transport)
            : Closure::fromCallable([$this, 'curlTransport']);
    }

    public function transcribe(string $workspaceId, string $audioPath, string $filename, string $mime = 'audio/webm'): ?string
    {
        $key = $this->settings->getKey($workspaceId, 'openai');
        if ($key === null || trim($key) === '' || ! is_file($audioPath)) {
            return null;
        }

        $raw = ($this->transport)($key, $audioPath, $filename, $mime);
        if ($raw === null || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        $text = is_array($data) ? ($data['text'] ?? null) : null;

        return is_string($text) && trim($text) !== '' ? trim($text) : null;
    }

    /** Real transport: multipart POST to OpenAI's Whisper endpoint. Returns the raw body or null. */
    private function curlTransport(string $apiKey, string $audioPath, string $filename, string $mime): ?string
    {
        if (! function_exists('curl_init')) {
            return null;
        }

        $ch = curl_init(self::ENDPOINT);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
            CURLOPT_POSTFIELDS => [
                'model' => self::MODEL,
                'response_format' => 'json',
                'file' => new CURLFile($audioPath, $mime, $filename !== '' ? $filename : 'audio.webm'),
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        // Respect a standard outbound proxy if the host defines one (inert in
        // most production setups; lets sandboxed/proxied hosts reach the API).
        $proxy = getenv('HTTPS_PROXY') ?: getenv('https_proxy');
        if (is_string($proxy) && $proxy !== '') {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
            if (is_file('/root/.ccr/ca-bundle.crt')) {
                curl_setopt($ch, CURLOPT_CAINFO, '/root/.ccr/ca-bundle.crt');
            }
        }

        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($body !== false && $code >= 200 && $code < 300) ? (string) $body : null;
    }
}
