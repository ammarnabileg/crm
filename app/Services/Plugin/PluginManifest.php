<?php

declare(strict_types=1);

namespace App\Services\Plugin;

/**
 * Immutable plugin manifest (docs/51 Plugin SDK) — the registry metadata for a
 * plugin: identity, version, dependencies, declared permissions, required modules,
 * minimum platform version and license.
 */
final class PluginManifest
{
    /**
     * @param string[] $dependencies     other plugin keys this plugin needs enabled
     * @param array<int,array<string,string>> $permissions declared permission rows
     * @param string[] $requiredModules   system_modules keys this plugin needs
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $version,
        public readonly ?string $author = null,
        public readonly ?string $description = null,
        public readonly array $dependencies = [],
        public readonly array $permissions = [],
        public readonly array $requiredModules = [],
        public readonly string $minPlatformVersion = '0.0.0',
        public readonly string $license = 'proprietary',
    ) {
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(string $key, array $data): self
    {
        return new self(
            key: $key,
            name: (string) ($data['name'] ?? $key),
            version: (string) ($data['version'] ?? '1.0.0'),
            author: isset($data['author']) ? (string) $data['author'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            dependencies: array_values(array_filter((array) ($data['dependencies'] ?? []), 'is_string')),
            permissions: array_values((array) ($data['permissions'] ?? [])),
            requiredModules: array_values(array_filter((array) ($data['required_modules'] ?? []), 'is_string')),
            minPlatformVersion: (string) ($data['min_platform_version'] ?? '0.0.0'),
            license: (string) ($data['license'] ?? 'proprietary'),
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'key'                  => $this->key,
            'name'                 => $this->name,
            'version'              => $this->version,
            'author'               => $this->author,
            'description'          => $this->description,
            'dependencies'         => $this->dependencies,
            'permissions'          => $this->permissions,
            'required_modules'     => $this->requiredModules,
            'min_platform_version' => $this->minPlatformVersion,
            'license'              => $this->license,
        ];
    }
}
