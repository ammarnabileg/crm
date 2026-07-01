<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin;

use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Plugin\Exception\PluginValidationException;

/**
 * An immutable, strict Semantic Versioning 2.0.0 version.
 *
 * Parses `MAJOR.MINOR.PATCH` with an optional pre-release (`-alpha.1`) and build metadata (`+build.5`),
 * exactly as the SemVer 2.0.0 specification prescribes: numeric identifiers carry no leading zeroes,
 * pre-release identifiers are dot-separated alphanumerics/hyphens, and build metadata is ignored for
 * ordering. Comparison follows the SemVer precedence rules — a version with a pre-release is *lower*
 * than the same version without one, and pre-release identifiers compare numerically or lexically as
 * their content dictates. This is the basis for {@see VersionConstraint} satisfaction and for deciding
 * whether one plugin version supersedes another.
 */
final class SemanticVersion implements ValueObject
{
    /**
     * The strict SemVer 2.0.0 grammar (anchored), with named capture groups.
     */
    private const string PATTERN =
        '/^(?<major>0|[1-9]\d*)\.(?<minor>0|[1-9]\d*)\.(?<patch>0|[1-9]\d*)'
        . '(?:-(?<pre>(?:0|[1-9]\d*|\d*[A-Za-z-][0-9A-Za-z-]*)(?:\.(?:0|[1-9]\d*|\d*[A-Za-z-][0-9A-Za-z-]*))*))?'
        . '(?:\+(?<build>[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?$/';

    /**
     * @param int           $major         The major version (backwards-incompatible changes).
     * @param int           $minor         The minor version (backwards-compatible features).
     * @param int           $patch         The patch version (backwards-compatible fixes).
     * @param list<string>  $preRelease    The dot-separated pre-release identifiers (empty for a release).
     * @param list<string>  $buildMetadata The dot-separated build-metadata identifiers (ignored for ordering).
     */
    private function __construct(
        private readonly int $major,
        private readonly int $minor,
        private readonly int $patch,
        private readonly array $preRelease,
        private readonly array $buildMetadata,
    ) {
    }

    /**
     * Parse a version string into a {@see SemanticVersion}.
     *
     * @param string $version The version string, e.g. `1.2.3`, `2.0.0-rc.1`, `1.0.0+build.7`.
     *
     * @throws PluginValidationException When the string is not a valid SemVer 2.0.0 version.
     */
    public static function parse(string $version): self
    {
        $trimmed = trim($version);
        if (preg_match(self::PATTERN, $trimmed, $matches) !== 1) {
            throw PluginValidationException::invalidVersion($version);
        }

        $pre = ($matches['pre'] ?? '') === '' ? [] : explode('.', $matches['pre']);
        $build = ($matches['build'] ?? '') === '' ? [] : explode('.', $matches['build']);

        return new self(
            (int) $matches['major'],
            (int) $matches['minor'],
            (int) $matches['patch'],
            array_values($pre),
            array_values($build),
        );
    }

    /**
     * Construct directly from numeric components (no pre-release or build metadata).
     */
    public static function of(int $major, int $minor, int $patch): self
    {
        if ($major < 0 || $minor < 0 || $patch < 0) {
            throw PluginValidationException::invalidVersion(sprintf('%d.%d.%d', $major, $minor, $patch));
        }

        return new self($major, $minor, $patch, [], []);
    }

    /**
     * The major version component.
     */
    public function major(): int
    {
        return $this->major;
    }

    /**
     * The minor version component.
     */
    public function minor(): int
    {
        return $this->minor;
    }

    /**
     * The patch version component.
     */
    public function patch(): int
    {
        return $this->patch;
    }

    /**
     * The pre-release identifiers, or an empty list for a stable release.
     *
     * @return list<string>
     */
    public function preRelease(): array
    {
        return $this->preRelease;
    }

    /**
     * The build-metadata identifiers, or an empty list when none were given.
     *
     * @return list<string>
     */
    public function buildMetadata(): array
    {
        return $this->buildMetadata;
    }

    /**
     * Whether this version carries a pre-release tag (and is thus not a stable release).
     */
    public function isPreRelease(): bool
    {
        return $this->preRelease !== [];
    }

    /**
     * Compare this version to another by SemVer precedence.
     *
     * Build metadata is ignored. Returns a negative integer if this version is lower, zero if they
     * have equal precedence, and a positive integer if this version is higher.
     */
    public function compareTo(self $other): int
    {
        $core = $this->major <=> $other->major
            ?: $this->minor <=> $other->minor
            ?: $this->patch <=> $other->patch;
        if ($core !== 0) {
            return $core;
        }

        return $this->comparePreRelease($this->preRelease, $other->preRelease);
    }

    /**
     * Whether this version has strictly greater precedence than another.
     */
    public function isGreaterThan(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    /**
     * Whether this version has strictly lower precedence than another.
     */
    public function isLessThan(self $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    /**
     * Precedence equality (build metadata ignored, per SemVer).
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $this->compareTo($other) === 0;
    }

    /**
     * The canonical SemVer string, including any pre-release and build metadata.
     */
    public function __toString(): string
    {
        $version = sprintf('%d.%d.%d', $this->major, $this->minor, $this->patch);
        if ($this->preRelease !== []) {
            $version .= '-' . implode('.', $this->preRelease);
        }
        if ($this->buildMetadata !== []) {
            $version .= '+' . implode('.', $this->buildMetadata);
        }

        return $version;
    }

    /**
     * Apply SemVer pre-release precedence between two identifier lists.
     *
     * A version with no pre-release outranks one that has pre-release identifiers. Otherwise the lists
     * are compared identifier by identifier: numeric identifiers compare numerically and rank below
     * alphanumeric ones; when all shared identifiers are equal, the longer list wins.
     *
     * @param list<string> $left  This version's pre-release identifiers.
     * @param list<string> $right The other version's pre-release identifiers.
     */
    private function comparePreRelease(array $left, array $right): int
    {
        if ($left === [] && $right === []) {
            return 0;
        }
        if ($left === []) {
            return 1;
        }
        if ($right === []) {
            return -1;
        }

        $count = min(count($left), count($right));
        for ($i = 0; $i < $count; $i++) {
            $comparison = $this->compareIdentifier($left[$i], $right[$i]);
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return count($left) <=> count($right);
    }

    /**
     * Compare a single pair of pre-release identifiers per SemVer rules.
     */
    private function compareIdentifier(string $left, string $right): int
    {
        $leftNumeric = ctype_digit($left);
        $rightNumeric = ctype_digit($right);

        if ($leftNumeric && $rightNumeric) {
            return (int) $left <=> (int) $right;
        }
        if ($leftNumeric) {
            return -1;
        }
        if ($rightNumeric) {
            return 1;
        }

        return strcmp($left, $right);
    }
}
