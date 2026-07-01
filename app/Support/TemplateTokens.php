<?php

declare(strict_types=1);

namespace HaHireAI\Support;

/**
 * Resolves `{{token}}` placeholders against a payload map. Shared by the Workflow
 * Engine and its Action Executor (previously byte-identical copies). Tokens are
 * produced by the builder's variable picker, never typed by end users. A scalar
 * value is inserted as a string; a non-scalar is JSON-encoded; a missing token
 * becomes the empty string.
 */
final class TemplateTokens
{
    /** @param array<string, mixed> $payload */
    public static function resolve(string $value, array $payload): string
    {
        if (! str_contains($value, '{{')) {
            return $value;
        }

        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            static function (array $m) use ($payload): string {
                $v = $payload[$m[1]] ?? '';

                return is_scalar($v) ? (string) $v : (string) json_encode($v);
            },
            $value,
        );
    }
}
