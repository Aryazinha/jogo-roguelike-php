<?php

declare(strict_types=1);

namespace Jogo\Domain\RandomEvent;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;

/** Um evento aleatório de events.json: Horda, Chuva de XP ou Tesouro (RF35). */
final readonly class RandomEventDefinition
{
    /** @param list<EventEffect> $effects */
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public int $weight,
        public float $durationSeconds,
        public array $effects,
    ) {
        CombatValidation::requiredId($id, 'id');
        CombatValidation::requiredId($name, 'name');
        CombatValidation::positive($durationSeconds, 'durationSeconds');
        if ($weight < 1) {
            throw new InvalidArgumentException('weight deve ser inteiro maior ou igual a um.');
        }
        if ($effects === []) {
            throw new InvalidArgumentException('um evento precisa de pelo menos um efeito.');
        }
    }
}
