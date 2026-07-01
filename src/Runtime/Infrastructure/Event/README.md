# Runtime — Infrastructure / Event

## Purpose
Bridges an execution's recorded domain events onto the platform's PSR-14 event pipeline, so listeners
(automation, notifications, projections) react after the work is durable.

## Responsibilities
- `DispatchingExecutionEventPublisher` — implements `ExecutionEventPublisher` by dispatching each pulled
  event, in order, through `Nizam\Platform\Event\EventDispatcher`.

## Dependencies
- `Nizam\Platform\Event\EventDispatcher`; `Nizam\Kernel\Domain\DomainEvent`; the
  `Execution\Domain\Port\ExecutionEventPublisher` port.

## Public interfaces
- `DispatchingExecutionEventPublisher`.
