<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Results;

final class ScriptedResultsInput implements ResultsInputInterface
{
    /** @var list<ResultsInputEvent> */
    private array $events = [];

    public function enqueue(ResultsInputEvent $event): void
    {
        $this->events[] = $event;
    }

    /** @phpstan-impure */
    public function poll(): ResultsInputEvent
    {
        return array_shift($this->events)
            ?? new ResultsInputEvent;
    }
}
