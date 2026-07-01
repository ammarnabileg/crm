<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin;

use Nizam\Kernel\Domain\ValueObject;
use Nizam\Platform\Plugin\Exception\PluginManifestException;

/**
 * The immutable, self-validating descriptor published by every plugin.
 *
 * A manifest is the plugin's contract with the platform: it names the plugin, fixes its
 * {@see SemanticVersion} and {@see PluginKind}, points to the entry-point class the platform will
 * instantiate, declares the platform versions it supports, and enumerates the permissions,
 * capabilities and other plugins it depends on. It is the unit of discovery, validation, dependency
 * resolution and installation. The manifest validates its own shape on construction — a missing
 * required field, a malformed name, an unknown kind or a malformed dependency all raise
 * {@see PluginManifestException} — and round-trips losslessly through {@see self::fromArray()} /
 * {@see self::toArray()}, which is the on-disk `plugin.json` representation.
 */
final class PluginManifest implements ValueObject
{
    /**
     * The permitted manifest-name shape: kebab or dotted, lowercase alphanumerics.
     */
    private const string NAME_PATTERN = '/^[a-z0-9]+([._-][a-z0-9]+)*$/';

    /**
     * @param string                  $name                 The unique, lowercase kebab/dotted plugin identifier.
     * @param string                  $displayName          A human-readable name for UI.
     * @param SemanticVersion         $version              The plugin's own version.
     * @param PluginKind              $kind                 The capability kind this plugin contributes.
     * @param string                  $description          A human-readable description of the plugin.
     * @param string                  $author               The plugin author.
     * @param string                  $license              The SPDX (or free-form) licence identifier.
     * @param string                  $entryPointClass      The FQCN the platform instantiates for this plugin.
     * @param VersionConstraint       $platformConstraint   The range of platform versions supported.
     * @param list<PluginPermission>  $requiredPermissions  The permissions the plugin needs granted.
     * @param list<string>            $requiredCapabilities  The platform capabilities the plugin needs present.
     * @param list<PluginDependency>  $dependencies         The other plugins this plugin depends on.
     * @param array<string, mixed>    $configSchema         A declarative schema describing the plugin's config.
     * @param string|null             $healthCheckClass     Optional FQCN of a {@see Port\HealthCheck} implementation.
     * @param list<string>            $tags                 Free-form tags for search and grouping.
     */
    private function __construct(
        private readonly string $name,
        private readonly string $displayName,
        private readonly SemanticVersion $version,
        private readonly PluginKind $kind,
        private readonly string $description,
        private readonly string $author,
        private readonly string $license,
        private readonly string $entryPointClass,
        private readonly VersionConstraint $platformConstraint,
        private readonly array $requiredPermissions,
        private readonly array $requiredCapabilities,
        private readonly array $dependencies,
        private readonly array $configSchema,
        private readonly ?string $healthCheckClass,
        private readonly array $tags,
    ) {
    }

    /**
     * Construct a manifest from typed components, validating the required fields.
     *
     * @param list<PluginPermission> $requiredPermissions  The permissions the plugin needs granted.
     * @param list<string>           $requiredCapabilities The platform capabilities the plugin needs present.
     * @param list<PluginDependency> $dependencies         The other plugins this plugin depends on.
     * @param array<string, mixed>   $configSchema         A declarative schema describing the plugin's config.
     * @param list<string>           $tags                 Free-form tags for search and grouping.
     *
     * @throws PluginManifestException When a required field is empty or malformed.
     */
    public static function create(
        string $name,
        string $displayName,
        SemanticVersion $version,
        PluginKind $kind,
        string $description,
        string $author,
        string $license,
        string $entryPointClass,
        VersionConstraint $platformConstraint,
        array $requiredPermissions = [],
        array $requiredCapabilities = [],
        array $dependencies = [],
        array $configSchema = [],
        ?string $healthCheckClass = null,
        array $tags = [],
    ): self {
        $normalizedName = trim($name);
        if ($normalizedName === '') {
            throw PluginManifestException::missingField('name');
        }
        if (preg_match(self::NAME_PATTERN, $normalizedName) !== 1) {
            throw PluginManifestException::invalidName($name);
        }
        if (trim($displayName) === '') {
            throw PluginManifestException::missingField('displayName');
        }
        if (trim($entryPointClass) === '') {
            throw PluginManifestException::missingField('entryPointClass');
        }
        if (trim($author) === '') {
            throw PluginManifestException::missingField('author');
        }
        if (trim($license) === '') {
            throw PluginManifestException::missingField('license');
        }

        $healthCheck = $healthCheckClass === null ? null : trim($healthCheckClass);
        if ($healthCheck === '') {
            $healthCheck = null;
        }

        return new self(
            name: $normalizedName,
            displayName: trim($displayName),
            version: $version,
            kind: $kind,
            description: $description,
            author: trim($author),
            license: trim($license),
            entryPointClass: ltrim(trim($entryPointClass), '\\'),
            platformConstraint: $platformConstraint,
            requiredPermissions: array_values($requiredPermissions),
            requiredCapabilities: self::normalizeStringList($requiredCapabilities, 'requiredCapabilities'),
            dependencies: array_values($dependencies),
            configSchema: $configSchema,
            healthCheckClass: $healthCheck === null ? null : ltrim($healthCheck, '\\'),
            tags: self::normalizeStringList($tags, 'tags'),
        );
    }

    /**
     * Reconstruct a manifest from its array (`plugin.json`) representation.
     *
     * @param array<string, mixed> $data The decoded manifest data.
     *
     * @throws PluginManifestException When a required field is absent, of the wrong type, or malformed.
     */
    public static function fromArray(array $data): self
    {
        $name = self::requireString($data, 'name');
        $displayName = self::requireString($data, 'displayName');
        $versionString = self::requireString($data, 'version');
        $kindString = self::requireString($data, 'kind');
        $entryPoint = self::requireString($data, 'entryPointClass');
        $author = self::requireString($data, 'author');
        $license = self::requireString($data, 'license');
        $platform = self::requireString($data, 'platformConstraint');

        $kind = PluginKind::tryFrom($kindString);
        if ($kind === null) {
            throw PluginManifestException::unknownKind($kindString);
        }

        return self::create(
            name: $name,
            displayName: $displayName,
            version: SemanticVersion::parse($versionString),
            kind: $kind,
            description: self::optionalString($data, 'description'),
            author: $author,
            license: $license,
            entryPointClass: $entryPoint,
            platformConstraint: VersionConstraint::parse($platform),
            requiredPermissions: self::parsePermissions($data),
            requiredCapabilities: self::parseStringArray($data, 'requiredCapabilities'),
            dependencies: self::parseDependencies($data),
            configSchema: self::parseAssoc($data, 'configSchema'),
            healthCheckClass: self::nullableString($data, 'healthCheckClass'),
            tags: self::parseStringArray($data, 'tags'),
        );
    }

    /**
     * The lossless array (`plugin.json`) representation of this manifest.
     *
     * @return array{
     *     name: string,
     *     displayName: string,
     *     version: string,
     *     kind: string,
     *     description: string,
     *     author: string,
     *     license: string,
     *     entryPointClass: string,
     *     platformConstraint: string,
     *     requiredPermissions: list<array{key: string, description: string}>,
     *     requiredCapabilities: list<string>,
     *     dependencies: list<array{plugin: string, constraint: string, optional: bool}>,
     *     configSchema: array<string, mixed>,
     *     healthCheckClass: string|null,
     *     tags: list<string>
     * }
     */
    public function toArray(): array
    {
        $permissions = [];
        foreach ($this->requiredPermissions as $permission) {
            $permissions[] = ['key' => $permission->key(), 'description' => $permission->description()];
        }

        $dependencies = [];
        foreach ($this->dependencies as $dependency) {
            $dependencies[] = [
                'plugin' => $dependency->pluginName(),
                'constraint' => $dependency->constraint()->raw(),
                'optional' => $dependency->isOptional(),
            ];
        }

        return [
            'name' => $this->name,
            'displayName' => $this->displayName,
            'version' => (string) $this->version,
            'kind' => $this->kind->value,
            'description' => $this->description,
            'author' => $this->author,
            'license' => $this->license,
            'entryPointClass' => $this->entryPointClass,
            'platformConstraint' => $this->platformConstraint->raw(),
            'requiredPermissions' => $permissions,
            'requiredCapabilities' => $this->requiredCapabilities,
            'dependencies' => $dependencies,
            'configSchema' => $this->configSchema,
            'healthCheckClass' => $this->healthCheckClass,
            'tags' => $this->tags,
        ];
    }

    /**
     * The unique, lowercase kebab/dotted plugin identifier.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * The human-readable display name.
     */
    public function displayName(): string
    {
        return $this->displayName;
    }

    /**
     * The plugin's own version.
     */
    public function version(): SemanticVersion
    {
        return $this->version;
    }

    /**
     * The capability kind this plugin contributes.
     */
    public function kind(): PluginKind
    {
        return $this->kind;
    }

    /**
     * The human-readable description.
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * The plugin author.
     */
    public function author(): string
    {
        return $this->author;
    }

    /**
     * The licence identifier.
     */
    public function license(): string
    {
        return $this->license;
    }

    /**
     * The fully-qualified entry-point class the platform instantiates.
     *
     * @return class-string|string
     */
    public function entryPointClass(): string
    {
        return $this->entryPointClass;
    }

    /**
     * The range of platform versions this plugin supports.
     */
    public function platformConstraint(): VersionConstraint
    {
        return $this->platformConstraint;
    }

    /**
     * The permissions the plugin needs granted.
     *
     * @return list<PluginPermission>
     */
    public function requiredPermissions(): array
    {
        return $this->requiredPermissions;
    }

    /**
     * The required permissions as a {@see PermissionSet}.
     */
    public function requiredPermissionSet(): PermissionSet
    {
        return PermissionSet::of($this->requiredPermissions);
    }

    /**
     * The platform capabilities the plugin needs present.
     *
     * @return list<string>
     */
    public function requiredCapabilities(): array
    {
        return $this->requiredCapabilities;
    }

    /**
     * The other plugins this plugin depends on.
     *
     * @return list<PluginDependency>
     */
    public function dependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * The declarative schema describing the plugin's configuration.
     *
     * @return array<string, mixed>
     */
    public function configSchema(): array
    {
        return $this->configSchema;
    }

    /**
     * The optional FQCN of a {@see Port\HealthCheck} implementation, or null when none is declared.
     */
    public function healthCheckClass(): ?string
    {
        return $this->healthCheckClass;
    }

    /**
     * The free-form tags for search and grouping.
     *
     * @return list<string>
     */
    public function tags(): array
    {
        return $this->tags;
    }

    /**
     * Value equality by name and version (the plugin identity across the registry).
     */
    public function equals(ValueObject $other): bool
    {
        return $other instanceof self
            && $this->name === $other->name
            && $this->version->equals($other->version);
    }

    /**
     * Require a non-empty string field from raw data.
     *
     * @param array<string, mixed> $data
     *
     * @throws PluginManifestException When absent, non-string, or empty.
     */
    private static function requireString(array $data, string $field): string
    {
        if (!array_key_exists($field, $data)) {
            throw PluginManifestException::missingField($field);
        }
        if (!is_string($data[$field])) {
            throw PluginManifestException::invalidField($field, 'a string');
        }
        if (trim($data[$field]) === '') {
            throw PluginManifestException::missingField($field);
        }

        return $data[$field];
    }

    /**
     * Read an optional string field, defaulting to an empty string.
     *
     * @param array<string, mixed> $data
     *
     * @throws PluginManifestException When present but not a string.
     */
    private static function optionalString(array $data, string $field): string
    {
        if (!array_key_exists($field, $data)) {
            return '';
        }
        if (!is_string($data[$field])) {
            throw PluginManifestException::invalidField($field, 'a string');
        }

        return $data[$field];
    }

    /**
     * Read a nullable string field.
     *
     * @param array<string, mixed> $data
     *
     * @throws PluginManifestException When present but not a string or null.
     */
    private static function nullableString(array $data, string $field): ?string
    {
        if (!array_key_exists($field, $data) || $data[$field] === null) {
            return null;
        }
        if (!is_string($data[$field])) {
            throw PluginManifestException::invalidField($field, 'a string or null');
        }
        $value = trim($data[$field]);

        return $value === '' ? null : $value;
    }

    /**
     * Read an associative-array field, defaulting to an empty array.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     *
     * @throws PluginManifestException When present but not an array.
     */
    private static function parseAssoc(array $data, string $field): array
    {
        if (!array_key_exists($field, $data)) {
            return [];
        }
        if (!is_array($data[$field])) {
            throw PluginManifestException::invalidField($field, 'an object');
        }

        /** @var array<string, mixed> */
        return $data[$field];
    }

    /**
     * Read a list-of-strings field, defaulting to an empty list.
     *
     * @param array<string, mixed> $data
     *
     * @return list<string>
     *
     * @throws PluginManifestException When present but not a list of strings.
     */
    private static function parseStringArray(array $data, string $field): array
    {
        if (!array_key_exists($field, $data)) {
            return [];
        }
        if (!is_array($data[$field])) {
            throw PluginManifestException::invalidField($field, 'an array of strings');
        }

        return self::normalizeStringList($data[$field], $field);
    }

    /**
     * Validate and normalize an arbitrary array into a list of non-empty strings.
     *
     * @param array<array-key, mixed> $values
     *
     * @return list<string>
     *
     * @throws PluginManifestException When any element is not a string.
     */
    private static function normalizeStringList(array $values, string $field): array
    {
        $normalized = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                throw PluginManifestException::invalidField($field, 'an array of strings');
            }
            $trimmed = trim($value);
            if ($trimmed !== '') {
                $normalized[] = $trimmed;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Parse the `requiredPermissions` field into permission value objects.
     *
     * @param array<string, mixed> $data
     *
     * @return list<PluginPermission>
     *
     * @throws PluginManifestException When the shape is invalid.
     */
    private static function parsePermissions(array $data): array
    {
        if (!array_key_exists('requiredPermissions', $data)) {
            return [];
        }
        if (!is_array($data['requiredPermissions'])) {
            throw PluginManifestException::invalidField('requiredPermissions', 'an array');
        }

        $permissions = [];
        foreach ($data['requiredPermissions'] as $entry) {
            if (is_string($entry)) {
                $permissions[] = new PluginPermission($entry);
                continue;
            }
            if (!is_array($entry) || !isset($entry['key']) || !is_string($entry['key'])) {
                throw PluginManifestException::invalidField('requiredPermissions', 'an array of permission objects');
            }
            $description = isset($entry['description']) && is_string($entry['description'])
                ? $entry['description']
                : '';
            $permissions[] = new PluginPermission($entry['key'], $description);
        }

        return $permissions;
    }

    /**
     * Parse the `dependencies` field into dependency value objects.
     *
     * @param array<string, mixed> $data
     *
     * @return list<PluginDependency>
     *
     * @throws PluginManifestException When the shape is invalid.
     */
    private static function parseDependencies(array $data): array
    {
        if (!array_key_exists('dependencies', $data)) {
            return [];
        }
        if (!is_array($data['dependencies'])) {
            throw PluginManifestException::invalidField('dependencies', 'an array');
        }

        $dependencies = [];
        foreach ($data['dependencies'] as $entry) {
            if (!is_array($entry) || !isset($entry['plugin']) || !is_string($entry['plugin'])) {
                throw PluginManifestException::invalidField('dependencies', 'an array of dependency objects');
            }
            $constraint = isset($entry['constraint']) && is_string($entry['constraint'])
                ? $entry['constraint']
                : '*';
            $optional = isset($entry['optional']) && $entry['optional'] === true;
            $dependencies[] = new PluginDependency(
                $entry['plugin'],
                VersionConstraint::parse($constraint),
                $optional,
            );
        }

        return $dependencies;
    }
}
