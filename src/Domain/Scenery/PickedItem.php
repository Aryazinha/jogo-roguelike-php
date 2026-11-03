<?php

declare(strict_types=1);

namespace Jogo\Domain\Scenery;

use Jogo\Domain\Chest\HealingItem;
use Jogo\Domain\Value\CombatValidation;

/** Registro de um item pego pelo jogador, para a Frente B aplicar e a D mostrar. */
final readonly class PickedItem
{
    public function __construct(
        public string $pickupId,
        public string $itemId,
        public ?HealingItem $healing,
        public ?TemporaryBonus $bonus,
        public float $pickedAtSeconds,
    ) {
        CombatValidation::requiredId($pickupId, 'pickupId');
        CombatValidation::requiredId($itemId, 'itemId');
        CombatValidation::nonNegative($pickedAtSeconds, 'pickedAtSeconds');
    }
}
