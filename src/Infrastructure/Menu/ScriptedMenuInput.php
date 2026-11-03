<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Menu;

final class ScriptedMenuInput implements MenuInputInterface
{
    /** @var list<MenuInputEvent> */
    private array $events = [];

    public function enqueue(MenuInputEvent $event): void
    {
        $this->events[] = $event;
    }

    /** @phpstan-impure */
    public function poll(): MenuInputEvent
    {
        return array_shift($this->events) ?? new MenuInputEvent;
    }
}
