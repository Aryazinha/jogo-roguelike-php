<?php

declare(strict_types=1);

namespace Jogo\Domain\RandomEvent;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;

/** Eventos de events.json e o intervalo, em segundos inteiros, entre o fim de um e o começo do próximo. */
final readonly class EventSettings
{
    /** @var list<RandomEventDefinition> */
    public array $events;

    /** @param list<RandomEventDefinition> $events */
    public function __construct(public int $minGapSeconds, public int $maxGapSeconds, array $events)
    {
        CombatValidation::positive((float) $minGapSeconds, 'minGapSeconds');
        if ($maxGapSeconds < $minGapSeconds) {
            throw new InvalidArgumentException('maxGapSeconds deve ser maior ou igual a minGapSeconds.');
        }
        // O RF35 pede no mínimo 2 eventos.
        if (count($events) < 2) {
            throw new InvalidArgumentException('O RF35 exige pelo menos 2 eventos aleatórios.');
        }
        $ids = array_map(static fn (RandomEventDefinition $e) => $e->id, $events);
        if (count(array_unique($ids)) !== count($ids)) {
            throw new InvalidArgumentException('ID de evento repetido.');
        }
        $this->events = $events;
    }

    public function totalWeight(): int
    {
        return array_sum(array_map(static fn (RandomEventDefinition $e) => $e->weight, $this->events));
    }
}
