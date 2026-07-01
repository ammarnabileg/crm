<?php

declare(strict_types=1);

namespace Nizam\Platform\Plugin\Service;

use Nizam\Platform\Plugin\Exception\PluginDependencyException;
use Nizam\Platform\Plugin\PluginDependency;
use Nizam\Platform\Plugin\PluginManifest;

/**
 * Computes a safe install order for a set of plugins from their declared dependencies.
 *
 * Given the manifests available to install, the resolver produces a topological ordering in which every
 * plugin appears after all the plugins it (required-)depends on, so installing the list front-to-back
 * never installs a plugin before something it needs. It enforces the platform's dependency rules while
 * doing so: a required dependency that is absent from the available set is a hard error
 * ({@see PluginDependencyException::missingRequired()}); a required dependency that is present but whose
 * available version does not satisfy the declared constraint is a hard error
 * ({@see PluginDependencyException::incompatibleRequired()}); and a cycle in the required-dependency
 * graph is a hard error ({@see PluginDependencyException::cycleDetected()}). Optional dependencies never
 * cause a failure: an optional dependency that is missing or unsatisfiable is simply skipped, though an
 * optional dependency that *is* present and satisfiable still constrains the order so it installs first.
 * The resolution is deterministic: among plugins with no outstanding dependencies, the earlier-declared
 * one is emitted first, so the same input always yields the same order.
 */
final class PluginDependencyResolver
{
    /**
     * Resolve a topological install order over the available plugins.
     *
     * @param list<PluginManifest> $available The manifests available to install.
     *
     * @return list<PluginManifest> The manifests in a safe install order (dependencies first).
     *
     * @throws PluginDependencyException When a required dependency is missing or incompatible, or the
     *                                   required-dependency graph contains a cycle.
     */
    public function resolveOrder(array $available): array
    {
        $byName = $this->indexByName($available);
        $edges = $this->buildEdges($available, $byName);

        return $this->topologicallySort($available, $byName, $edges);
    }

    /**
     * Index the available manifests by their unique name, rejecting duplicate names.
     *
     * @param list<PluginManifest> $available
     *
     * @return array<string, PluginManifest>
     *
     * @throws PluginDependencyException When two manifests share a name.
     */
    private function indexByName(array $available): array
    {
        $byName = [];
        foreach ($available as $manifest) {
            $name = $manifest->name();
            if (isset($byName[$name])) {
                throw PluginDependencyException::cycleDetected([$name, $name]);
            }
            $byName[$name] = $manifest;
        }

        return $byName;
    }

    /**
     * Build the dependency edges (dependant name => list of resolvable dependency names) for each plugin.
     *
     * Required dependencies must be present and satisfiable; optional ones are included only when they
     * are present and satisfiable, and skipped otherwise.
     *
     * @param list<PluginManifest>          $available
     * @param array<string, PluginManifest> $byName
     *
     * @return array<string, list<string>>
     *
     * @throws PluginDependencyException When a required dependency is missing or incompatible.
     */
    private function buildEdges(array $available, array $byName): array
    {
        $edges = [];
        foreach ($available as $manifest) {
            $dependencyNames = [];
            foreach ($manifest->dependencies() as $dependency) {
                $resolved = $this->resolveDependency($manifest, $dependency, $byName);
                if ($resolved !== null) {
                    $dependencyNames[] = $resolved;
                }
            }
            $edges[$manifest->name()] = array_values(array_unique($dependencyNames));
        }

        return $edges;
    }

    /**
     * Resolve a single dependency to the name it constrains the order by, or null when it may be skipped.
     *
     * @param array<string, PluginManifest> $byName
     *
     * @return string|null The dependency's name when it must precede the dependant, or null when an
     *                     optional dependency is absent or unsatisfiable and thus skipped.
     *
     * @throws PluginDependencyException When a required dependency is missing or incompatible.
     */
    private function resolveDependency(
        PluginManifest $manifest,
        PluginDependency $dependency,
        array $byName,
    ): ?string {
        $target = $byName[$dependency->pluginName()] ?? null;

        if ($target === null) {
            if ($dependency->isOptional()) {
                return null;
            }

            throw PluginDependencyException::missingRequired($manifest->name(), $dependency->pluginName());
        }

        if (!$dependency->isSatisfiedBy($target->version())) {
            if ($dependency->isOptional()) {
                return null;
            }

            throw PluginDependencyException::incompatibleRequired(
                $manifest->name(),
                $dependency->pluginName(),
                $dependency->constraint()->raw(),
            );
        }

        return $target->name();
    }

    /**
     * Produce a deterministic topological order via depth-first search with cycle detection.
     *
     * @param list<PluginManifest>          $available
     * @param array<string, PluginManifest> $byName
     * @param array<string, list<string>>   $edges
     *
     * @return list<PluginManifest>
     *
     * @throws PluginDependencyException When a cycle is detected.
     */
    private function topologicallySort(array $available, array $byName, array $edges): array
    {
        /** @var array<string, true> $permanent Fully-visited nodes already emitted. */
        $permanent = [];
        /** @var array<string, true> $temporary Nodes on the current DFS stack (cycle detection). */
        $temporary = [];
        /** @var list<string> $path The current DFS path, for reporting a cycle. */
        $path = [];
        /** @var list<PluginManifest> $ordered */
        $ordered = [];

        $visit = function (string $name) use (&$visit, &$permanent, &$temporary, &$path, &$ordered, $edges, $byName): void {
            if (isset($permanent[$name])) {
                return;
            }
            if (isset($temporary[$name])) {
                $cycleStart = array_search($name, $path, true);
                $cycle = $cycleStart === false ? [$name] : array_slice($path, (int) $cycleStart);
                $cycle[] = $name;

                throw PluginDependencyException::cycleDetected(array_values($cycle));
            }

            $temporary[$name] = true;
            $path[] = $name;

            foreach ($edges[$name] as $dependencyName) {
                $visit($dependencyName);
            }

            array_pop($path);
            unset($temporary[$name]);
            $permanent[$name] = true;
            $ordered[] = $byName[$name];
        };

        foreach ($available as $manifest) {
            $visit($manifest->name());
        }

        return $ordered;
    }
}
