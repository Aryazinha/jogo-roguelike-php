<?php

declare(strict_types=1);

namespace Jogo\Domain\Event;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;

/** Publicado uma vez por nível alcançado; payload proposto pela B, a confirmar com D. */
final readonly class LevelUp implements DomainEvent
{
    public function __construct(
        public string $playerId,
        public int $level,
        public int $totalXp,
        public float $elapsedSeconds,
    ) {
        CombatValidation::requiredId($playerId, 'playerId');
        if ($level < 2 || $totalXp < 0) {
            throw new InvalidArgumentException('LevelUp exige nível >= 2 e XP total >= 0.');
        }
        CombatValidation::nonNegative($elapsedSeconds, 'elapsedSeconds');
    }
}
