<?php

declare(strict_types=1);

namespace Jogo\Domain\Event;

use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Vector2;

final readonly class PlayerDamaged implements DomainEvent
{
    public function __construct(
        public string $playerId,
        public Vector2 $position,
        public float $appliedDamage,
        public float $remainingHealth,
        public ?string $sourceEntityId,
        public float $elapsedSeconds,
    ) {
        CombatValidation::requiredId($playerId, 'playerId');
        CombatValidation::optionalId($sourceEntityId, 'sourceEntityId');
        CombatValidation::positive($appliedDamage, 'appliedDamage');
        CombatValidation::nonNegative($remainingHealth, 'remainingHealth');
        CombatValidation::nonNegative($elapsedSeconds, 'elapsedSeconds');
    }
}
