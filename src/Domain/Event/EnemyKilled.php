<?php

declare(strict_types=1);

namespace Jogo\Domain\Event;

use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Vector2;

final readonly class EnemyKilled implements DomainEvent
{
    public function __construct(
        public string $enemyId,
        public string $enemyTypeId,
        public bool $isBoss,
        public Vector2 $position,
        public ?string $sourceEntityId,
        public ?string $weaponId,
        public float $elapsedSeconds,
    ) {
        CombatValidation::requiredId($enemyId, 'enemyId');
        CombatValidation::requiredId($enemyTypeId, 'enemyTypeId');
        CombatValidation::optionalId($sourceEntityId, 'sourceEntityId');
        CombatValidation::optionalId($weaponId, 'weaponId');
        CombatValidation::nonNegative($elapsedSeconds, 'elapsedSeconds');
    }
}
