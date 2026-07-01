<?php

declare(strict_types=1);

namespace Nizam\Platform\Support;

use JsonException;
use Nizam\Platform\Exception\PlatformException;

/**
 * Strict JSON encoder/decoder that fails loudly.
 *
 * PHP's native {@see \json_encode()}/{@see \json_decode()} return `false`/`null` on error unless
 * `JSON_THROW_ON_ERROR` is set. This helper always uses that flag and rethrows as a
 * {@see PlatformException}, so callers never have to inspect {@see \json_last_error()} and a
 * malformed payload can never silently become `null`.
 */
final class Json
{
    /**
     * Non-instantiable static utility.
     */
    private function __construct()
    {
    }

    /**
     * Encode a value to a JSON string.
     *
     * @param bool $pretty Whether to pretty-print with indentation.
     *
     * @throws PlatformException When the value cannot be encoded.
     */
    public static function encode(mixed $value, bool $pretty = false): string
    {
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        try {
            return json_encode($value, $flags);
        } catch (JsonException $e) {
            throw new PlatformException('Failed to encode value as JSON: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Decode a JSON string into an associative array.
     *
     * @return array<array-key, mixed>
     *
     * @throws PlatformException When the string is not valid JSON or does not decode to an array.
     */
    public static function decode(string $json): array
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new PlatformException('Failed to decode JSON string: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($decoded)) {
            throw new PlatformException('Decoded JSON is not an array/object.');
        }

        return $decoded;
    }

    /**
     * Decode a JSON string into its natural PHP type (scalar, array, or null).
     *
     * @throws PlatformException When the string is not valid JSON.
     */
    public static function decodeValue(string $json): mixed
    {
        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new PlatformException('Failed to decode JSON string: ' . $e->getMessage(), 0, $e);
        }
    }
}
