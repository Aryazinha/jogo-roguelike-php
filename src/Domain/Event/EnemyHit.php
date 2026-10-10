<?php

declare(strict_types=1);

namespace Jogo\Domain\Event;

use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Vector2;

final readonly class EnemyHit implements DomainEvent
{
    public function __construct(
        public string $enemyId,
        public string $enemyTypeId,
        public Vector2 $position,
        public float $appliedDamage,
        public float $remainingHealth,
        public ?string $sourceEntityId,
        public ?string $weaponId,
        public float $elapsedSeconds,
    ) {
        CombatValidation::requiredId($enemyId, 'enemyId');
        CombatValidation::requiredId($enemyTypeId, 'enemyTypeId');
        CombatValidation::optionalId($sourceEntityId, 'sourceEntityId');
        CombatValidation::optionalId($weaponId, 'weaponId');
        CombatValidation::positive($appliedDamage, 'appliedDamage');
        CombatValidation::nonNegative($remainingHealth, 'remainingHealth');
        CombatValidation::nonNegative($elapsedSeconds, 'elapsedSeconds');
    }
}
