<?php

declare(strict_types=1);

namespace Jogo\Core;

use Closure;
use Jogo\Domain\Event\DomainEvent;
use Jogo\Domain\Event\EventBusInterface;
use ReflectionClass;

/** Instância por partida; publicação síncrona para a classe exata, sem estado global. */
final class EventBus implements EventBusInterface
{
    /** @var array<class-string<DomainEvent>, list<Closure(DomainEvent): void>> */
    private array $listeners = [];

    /**
     * @template T of DomainEvent
     *
     * @param  class-string<T>  $eventClass
     * @param  callable(T): void  $listener
     */
    public function subscribe(string $eventClass, callable $listener): void
    {
        // A identidade da classe é canônica, inclusive se vier com barra inicial ou caixa diferente.
        $eventClass = (new ReflectionClass($eventClass))->getName();
        $this->listeners[$eventClass][] = static function (DomainEvent $event) use ($eventClass, $listener): void {
            if ($event instanceof $eventClass) {
                $listener($event);
            }
        };
    }

    public function publish(DomainEvent $event): void
    {
        // A lista desta publicação é estável; novas inscrições valem na próxima publicação.
        foreach ($this->listeners[$event::class] ?? [] as $listener) {
            $listener($event);
        }
    }
}
