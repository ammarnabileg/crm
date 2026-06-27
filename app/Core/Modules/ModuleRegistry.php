<?php

declare(strict_types=1);

namespace HaHireAI\Core\Modules;

use HaHireAI\Core\Modules\Exceptions\ModuleException;

/**
 * Explicit registry of enabled modules. Modules register themselves here (no
 * filesystem auto-discovery). The registry resolves a dependency-ordered boot
 * sequence and rejects cycles. See docs/MODULES.md.
 */
final class ModuleRegistry
{
    /** @var array<string, Module> */
    private array $modules = [];

    public function add(Module $module): void
    {
        $this->modules[$module->name()] = $module;
    }

    public function has(string $name): bool
    {
        return isset($this->modules[$name]);
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->modules);
    }

    /** @return array<string, Module> */
    public function all(): array
    {
        return $this->modules;
    }

    public function count(): int
    {
        return count($this->modules);
    }

    /**
     * Return modules in dependency order (dependencies before dependents).
     *
     * @return list<Module>
     */
    public function ordered(): array
    {
        $ordered = [];
        $visiting = [];

        $visit = function (string $name) use (&$visit, &$ordered, &$visiting): void {
            if (isset($ordered[$name])) {
                return;
            }

            if (isset($visiting[$name])) {
                throw new ModuleException("Circular module dependency involving [{$name}].");
            }

            $module = $this->modules[$name]
                ?? throw new ModuleException("Module [{$name}] depends on a module that is not registered.");

            $visiting[$name] = true;

            foreach ($module->dependencies() as $dependency) {
                $visit($dependency);
            }

            unset($visiting[$name]);
            $ordered[$name] = $module;
        };

        foreach (array_keys($this->modules) as $name) {
            $visit($name);
        }

        return array_values($ordered);
    }
}
