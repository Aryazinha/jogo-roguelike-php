<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon;

use Jogo\Domain\Entity\Damageable;
use Jogo\Domain\Entity\Entity;
use Jogo\Domain\Value\CombatValidation;

/** Porta de escrita da A5: metadados de conteúdo são fornecidos externamente pela C. */
final readonly class EnemyTarget
{
    public function __construct(public Entity&Damageable $entity, public string $enemyTypeId, public bool $isBoss)
    {
        CombatValidation::requiredId($enemyTypeId, 'enemyTypeId');
    }
}
