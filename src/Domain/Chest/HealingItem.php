<?php

declare(strict_types=1);

namespace Jogo\Domain\Chest;

use Jogo\Domain\Value\CombatValidation;

/** Item de cura do RF27 (Steak). A C só cria o item; quem cura o jogador é a Frente B. */
final readonly class HealingItem
{
    public function __construct(public string $id, public string $name, public float $heal)
    {
        CombatValidation::requiredId($id, 'id');
        CombatValidation::requiredId($name, 'name');
        CombatValidation::positive($heal, 'heal');
    }
}
