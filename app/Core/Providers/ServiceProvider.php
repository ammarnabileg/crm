<?php

declare(strict_types=1);

namespace HaHireAI\Core\Providers;

use HaHireAI\Core\Contracts\Container;

/**
 * Base service provider. Providers register bindings in register() and perform
 * any wiring that needs all bindings present in boot(). Two-phase by design.
 * See docs/SERVICE_CONTAINER.md, docs/BOOTSTRAP_FLOW.md.
 */
abstract class ServiceProvider
{
    public function __construct(protected readonly Container $container)
    {
    }

    /** Bind services into the container. Runs for every provider first. */
    public function register(): void
    {
    }

    /** Wire things up once all providers have registered. */
    public function boot(): void
    {
    }
}
