# Plugin\Infrastructure\Event

**Purpose.** The adapter that bridges pulled plugin domain events onto the platform event pipeline.

**Responsibilities.**
- `DispatchingPluginEventPublisher` — implements the `PluginEventPublisher` port by dispatching each
  pulled `DomainEvent`, in recorded order, through the platform PSR-14 `EventDispatcher`. Keeps the
  domain and application layers free of dispatch machinery.

**Dependencies.** `Nizam\Platform\Plugin\Port\PluginEventPublisher`, `Nizam\Platform\Event\EventDispatcher`,
`Nizam\Kernel\Domain\DomainEvent`, PHP.

**Public interfaces.** `DispatchingPluginEventPublisher implements PluginEventPublisher`.
