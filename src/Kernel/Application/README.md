# Kernel\Application

**Purpose.** The application-layer contracts for the CQRS-style command/query flow and simple in-memory buses.

**Responsibilities.**
- `Command`, `Query`, `UseCase` — marker interfaces.
- `CommandHandler`, `QueryHandler` — one-per-message handler contracts.
- `CommandBus`, `QueryBus` — dispatch contracts.
- `SimpleCommandBus`, `SimpleQueryBus` — in-memory buses with a one-to-one message→handler registry (`register`/`dispatch`/`hasHandlerFor`); throw on duplicate registration or unregistered dispatch.

**Dependencies.** `Nizam\Platform\Exception` (`PlatformException`).

**Public interfaces.** `Command`, `Query`, `UseCase`, `CommandHandler`, `QueryHandler`, `CommandBus`, `QueryBus`, `SimpleCommandBus`, `SimpleQueryBus`.
