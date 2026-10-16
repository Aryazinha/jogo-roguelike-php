<?php

declare(strict_types=1);

namespace Jogo\Domain\Character;

use Jogo\Domain\Value\CombatValidation;

/** Atributos iniciais de um personagem jogável (RF02, RF16, RF17), sem estado de partida. */
final readonly class CharacterDefinition
{
    public function __construct(
        public string $id,
        public string $name,
        public float $maxHealth,
        public float $regenerationHp,
        public float $regenerationIntervalSeconds,
        public string $startingWeaponId,
        public CharacterPassive $passive,
    ) {
        CombatValidation::requiredId($id, 'id do personagem');
        CombatValidation::requiredId($name, 'nome do personagem');
        CombatValidation::positive($maxHealth, 'vida');
        CombatValidation::positive($regenerationHp, 'regeneracao.hp');
        CombatValidation::positive($regenerationIntervalSeconds, 'regeneracao.intervaloSegundos');
        CombatValidation::requiredId($startingWeaponId, 'armaInicial');
    }
}
