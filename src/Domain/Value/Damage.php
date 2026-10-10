<?php

declare(strict_types=1);

namespace Jogo\Domain\Value;

/** Ataque já resolvido pelo chamador; não representa arma ou projétil concreto. */
final readonly class Damage
{
    public function __construct(
        public float $amount,
        public ?string $sourceEntityId = null,
        public ?string $weaponId = null,
    ) {
        CombatValidation::nonNegative($amount, 'O dano solicitado');
        CombatValidation::optionalId($sourceEntityId, 'sourceEntityId');
        CombatValidation::optionalId($weaponId, 'weaponId');
    }
}
