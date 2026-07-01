<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin;

use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Plugin\Exception\PluginValidationException;
use Nizam\Platform\Plugin\VersionBound as Bound;

/**
 * An immutable version constraint that a {@see SemanticVersion} may or may not satisfy.
 *
 * Supports the common constraint dialects used by plugin manifests:
 *  - the wildcard `*` (any version);
 *  - x-ranges such as `1.x`, `1.2.*` (any version within the fixed prefix);
 *  - the caret range `^1.2.3` (compatible-within-major, or within-minor / within-patch when the left
 *    non-zero component is minor or patch, per the usual caret semantics);
 *  - the tilde range `~1.2.3` (allows patch-level changes; `~1.2` allows minor-level changes);
 *  - explicit comparators `=`, `>`, `>=`, `<`, `<=` combined with whitespace as a logical AND, e.g.
 *    `>=1.0.0 <2.0.0`.
 *
 * A constraint is parsed once into a set of lower/upper bounds and answers {@see self::satisfies()} in
 * pure arithmetic over {@see SemanticVersion} precedence. Pre-release versions only satisfy a bound
 * when that bound itself pins the same major.minor.patch with a pre-release, matching conventional
 * range behaviour (a pre-release is not accidentally admitted by a stable range).
 */
final class VersionConstraint implements ValueObject
{
    /**
     * @param string       $raw    The original constraint string, preserved for display and round-trip.
     * @param list<Bound>  $bounds The conjunction of bounds a version must all satisfy (empty = any).
     */
    private function __construct(
        private readonly string $raw,
        private readonly array $bounds,
    ) {
    }

    /**
     * Parse a constraint string.
     *
     * @param string $constraint The constraint expression, e.g. `^1.2`, `~1.2.3`, `>=1.0.0 <2.0.0`, `1.x`, `*`.
     *
     * @throws PluginValidationException When the expression cannot be parsed.
     */
    public static function parse(string $constraint): self
    {
        $trimmed = trim($constraint);
        if ($trimmed === '' || $trimmed === '*') {
            return new self($trimmed === '' ? '*' : $trimmed, []);
        }

        $bounds = [];
        foreach (preg_split('/\s+/', $trimmed) ?: [] as $token) {
            if ($token === '') {
                continue;
            }
            foreach (self::boundsForToken($token, $constraint) as $bound) {
                $bounds[] = $bound;
            }
        }

        if ($bounds === []) {
            throw PluginValidationException::invalidConstraint($constraint);
        }

        return new self($trimmed, $bounds);
    }

    /**
     * Whether the given version satisfies every bound of this constraint.
     */
    public function satisfies(SemanticVersion $version): bool
    {
        foreach ($this->bounds as $bound) {
            if (!$bound->allows($version)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The original constraint string.
     */
    public function raw(): string
    {
        return $this->raw;
    }

    /**
     * Value equality by normalized constraint string.
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self && $this->raw === $other->raw;
    }

    /**
     * The original constraint string.
     */
    public function __toString(): string
    {
        return $this->raw;
    }

    /**
     * Expand a single constraint token into its lower/upper bounds.
     *
     * @return list<Bound>
     *
     * @throws PluginValidationException When the token is malformed.
     */
    private static function boundsForToken(string $token, string $original): array
    {
        if ($token[0] === '^') {
            return self::caretBounds(substr($token, 1), $original);
        }
        if ($token[0] === '~') {
            return self::tildeBounds(substr($token, 1), $original);
        }
        if (preg_match('/^(>=|<=|>|<|=)(.+)$/', $token, $m) === 1) {
            return [new Bound($m[1], SemanticVersion::parse($m[2]))];
        }
        if (self::isXRange($token)) {
            return self::xRangeBounds($token, $original);
        }

        // A bare version is an exact match.
        return [new Bound('=', SemanticVersion::parse($token))];
    }

    /**
     * Whether a token is an x-range (a dotted version-like token containing an `x`/`X`/`*` segment).
     */
    private static function isXRange(string $token): bool
    {
        foreach (explode('.', $token) as $segment) {
            if ($segment === 'x' || $segment === 'X' || $segment === '*') {
                return true;
            }
        }

        return false;
    }

    /**
     * Build bounds for an x-range such as `1.x` or `1.2.*`.
     *
     * @return list<Bound>
     *
     * @throws PluginValidationException When the range is malformed.
     */
    private static function xRangeBounds(string $token, string $original): array
    {
        $parts = explode('.', $token);
        if (count($parts) > 3) {
            throw PluginValidationException::invalidConstraint($original);
        }

        $numeric = [];
        foreach ($parts as $part) {
            if ($part === 'x' || $part === 'X' || $part === '*') {
                break;
            }
            if (!ctype_digit($part)) {
                throw PluginValidationException::invalidConstraint($original);
            }
            $numeric[] = (int) $part;
        }

        if ($numeric === []) {
            return [];
        }

        $lower = SemanticVersion::of(
            $numeric[0],
            $numeric[1] ?? 0,
            $numeric[2] ?? 0,
        );

        if (count($numeric) === 1) {
            $upper = SemanticVersion::of($numeric[0] + 1, 0, 0);
        } else {
            $upper = SemanticVersion::of($numeric[0], $numeric[1] + 1, 0);
        }

        return [new Bound('>=', $lower), new Bound('<', $upper)];
    }

    /**
     * Build bounds for a caret range `^x.y.z`.
     *
     * @return list<Bound>
     */
    private static function caretBounds(string $version, string $original): array
    {
        $lower = self::lowerBound($version, $original);
        [$major, $minor, $patch] = self::components($version, $original);

        if ($major > 0) {
            $upper = SemanticVersion::of($major + 1, 0, 0);
        } elseif ($minor > 0) {
            $upper = SemanticVersion::of(0, $minor + 1, 0);
        } else {
            $upper = SemanticVersion::of(0, 0, $patch + 1);
        }

        return [new Bound('>=', $lower), new Bound('<', $upper)];
    }

    /**
     * Build bounds for a tilde range `~x.y.z` or `~x.y`.
     *
     * @return list<Bound>
     */
    private static function tildeBounds(string $version, string $original): array
    {
        $lower = self::lowerBound($version, $original);
        [$major, $minor] = self::components($version, $original);
        $suppliedComponents = count(explode('.', explode('-', $version)[0]));

        if ($suppliedComponents >= 2) {
            $upper = SemanticVersion::of($major, $minor + 1, 0);
        } else {
            $upper = SemanticVersion::of($major + 1, 0, 0);
        }

        return [new Bound('>=', $lower), new Bound('<', $upper)];
    }

    /**
     * Build the inclusive lower-bound version for a caret/tilde token.
     *
     * A partial version such as `1.2` is padded to a full `1.2.0` before parsing, while any
     * pre-release / build suffix on the supplied string is preserved (e.g. `1.2.0-rc.1`).
     *
     * @throws PluginValidationException When the version string is malformed.
     */
    private static function lowerBound(string $version, string $original): SemanticVersion
    {
        [$major, $minor, $patch] = self::components($version, $original);
        $suffix = '';
        if (preg_match('/^[0-9.]+((?:-|\+).*)$/', $version, $m) === 1) {
            $suffix = $m[1];
        }

        return SemanticVersion::parse(sprintf('%d.%d.%d%s', $major, $minor, $patch, $suffix));
    }

    /**
     * Extract the numeric major/minor/patch of a version string, defaulting missing tails to zero.
     *
     * @return array{0: int, 1: int, 2: int}
     *
     * @throws PluginValidationException When the numeric core is malformed.
     */
    private static function components(string $version, string $original): array
    {
        $core = explode('-', $version, 2)[0];
        $core = explode('+', $core, 2)[0];
        $parts = explode('.', $core);
        if (count($parts) > 3) {
            throw PluginValidationException::invalidConstraint($original);
        }

        $numbers = [];
        foreach ($parts as $part) {
            if (!ctype_digit($part)) {
                throw PluginValidationException::invalidConstraint($original);
            }
            $numbers[] = (int) $part;
        }

        return [$numbers[0], $numbers[1] ?? 0, $numbers[2] ?? 0];
    }
}
