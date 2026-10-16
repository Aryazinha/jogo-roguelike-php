<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression\Reward;

use Jogo\Domain\Value\CombatValidation;

/** Item de cura do RF27 (Steak). */
final readonly class HealingItem
{
    public function __construct(public string $id, public string $name, public float $amount)
    {
        CombatValidation::requiredId($id, 'id do item de cura');
        CombatValidation::requiredId($name, 'nome do item de cura');
        CombatValidation::positive($amount, 'cura');
    }
}
