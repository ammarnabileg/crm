<?php

declare(strict_types=1);

namespace HaHireAI\Core\Modules;

use HaHireAI\Core\Contracts\Container;
use HaHireAI\Core\Routing\Router;

/**
 * Contract every module implements. A module is autonomous: it registers its
 * own services, boots, and declares routes. Modules are discovered ONLY via the
 * registry — never by scanning. See docs/MODULES.md, docs/ARCHITECTURE.md.
 */
interface Module
{
    /** Canonical module name (matches docs/MODULES.md). */
    public function name(): string;

    /** Names of modules this module depends on (must be acyclic). @return list<string> */
    public function dependencies(): array;

    /** Bind the module's services. */
    public function register(Container $container): void;

    /** Boot the module once all modules are registered. */
    public function boot(Container $container): void;

    /** Register the module's routes. */
    public function routes(Router $router): void;
}
