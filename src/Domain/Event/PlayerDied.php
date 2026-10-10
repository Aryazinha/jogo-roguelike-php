<?php

declare(strict_types=1);

namespace Jogo\Domain\Event;

use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Vector2;

final readonly class PlayerDied implements DomainEvent
{
    public function __construct(
        public string $playerId,
        public Vector2 $position,
        public ?string $sourceEntityId,
        public float $elapsedSeconds,
    ) {
        CombatValidation::requiredId($playerId, 'playerId');
        CombatValidation::optionalId($sourceEntityId, 'sourceEntityId');
        CombatValidation::nonNegative($elapsedSeconds, 'elapsedSeconds');
    }
}
