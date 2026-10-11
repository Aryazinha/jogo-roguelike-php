<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon\Upgrade;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Weapon\WeaponDefinition;

/** Requisitos são dados para a B; este objeto não avalia elegibilidade. */
final readonly class EvolutionDefinition
{
    public function __construct(public string $weaponId, public int $weaponLevel,
        public string $passiveId, public int $passiveLevel, public WeaponDefinition $definition)
    {
        CombatValidation::requiredId($weaponId, 'weaponId');
        CombatValidation::requiredId($passiveId, 'passiveId');
        if ($weaponLevel < 1 || $passiveLevel < 1 || $definition->id === $weaponId) {
            throw new InvalidArgumentException('Evolução exige níveis positivos e um novo ID de arma.');
        }
    }
}
