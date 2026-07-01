<?php

declare(strict_types=1);

namespace Nizam\Platform\Support;

use Nizam\Platform\Exception\InvalidArgumentException;

/**
 * Generator and validator for UUID version 7 identifiers (RFC 9562).
 *
 * UUID v7 is time-ordered: the leading 48 bits encode a Unix millisecond timestamp, which makes
 * the identifiers monotonically increasing and therefore index-friendly for primary keys, while
 * the trailing bits are cryptographically random. This is the platform's canonical id format
 * (see {@see \Nizam\Kernel\Domain\Identifier}).
 *
 * Layout (128 bits):
 *   - bits  0-47  : unix_ts_ms (big-endian milliseconds since the epoch)
 *   - bits 48-51  : version (0b0111 = 7)
 *   - bits 52-63  : rand_a (random)
 *   - bits 64-65  : variant (0b10)
 *   - bits 66-127 : rand_b (random)
 */
final class Uuid
{
    /**
     * Canonical 8-4-4-4-12 hexadecimal form, e.g. "018f...-...".
     */
    private const REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * Non-instantiable static utility.
     */
    private function __construct()
    {
    }

    /**
     * Generate a new UUID v7 in canonical lowercase string form.
     */
    public static function v7(): string
    {
        return self::format(self::v7Bytes());
    }

    /**
     * Generate a new UUID v7 as a raw 16-byte binary string.
     *
     * @return string 16 raw bytes.
     */
    public static function v7Bytes(): string
    {
        // Current Unix time in milliseconds as a 48-bit big-endian integer.
        $unixTsMs = (int) floor(microtime(true) * 1000);

        // Pack the 48-bit timestamp into the first 6 bytes (drop the two high zero bytes of the 8-byte pack).
        $timestamp = substr(pack('J', $unixTsMs), 2, 6);

        // 10 random bytes fill rand_a (partial) and rand_b.
        $random = random_bytes(10);

        $bytes = $timestamp . $random;

        // Set the version nibble to 0b0111 (7) in byte 6 (index 6), preserving its low nibble.
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x70);

        // Set the variant to 0b10xxxxxx in byte 8 (index 8).
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return $bytes;
    }

    /**
     * Whether the given string is a syntactically valid UUID (any RFC 9562 version).
     */
    public static function isValid(string $uuid): bool
    {
        return preg_match(self::REGEX, $uuid) === 1;
    }

    /**
     * Whether the given string is a valid version 7 UUID specifically.
     */
    public static function isV7(string $uuid): bool
    {
        return self::isValid($uuid) && $uuid[14] === '7';
    }

    /**
     * Format a raw 16-byte binary UUID into canonical lowercase 8-4-4-4-12 form.
     *
     * @param string $bytes Exactly 16 raw bytes.
     *
     * @throws InvalidArgumentException When the input is not exactly 16 bytes.
     */
    public static function format(string $bytes): string
    {
        if (strlen($bytes) !== 16) {
            throw new InvalidArgumentException(sprintf(
                'A binary UUID must be exactly 16 bytes, %d given.',
                strlen($bytes),
            ));
        }

        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
