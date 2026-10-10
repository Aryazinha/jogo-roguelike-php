<?php

declare(strict_types=1);

namespace Jogo\Domain\Event;

interface EventBusInterface
{
    /**
     * @template T of DomainEvent
     *
     * @param  class-string<T>  $eventClass
     * @param  callable(T): void  $listener
     */
    public function subscribe(string $eventClass, callable $listener): void;

    public function publish(DomainEvent $event): void;
}
