<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression\LevelUp;

/** Os três tipos de escolha do RF14. */
enum LevelUpOptionKind: string
{
    case NEW_WEAPON = 'nova_arma';
    case UPGRADE_WEAPON = 'melhoria_arma';
    case PASSIVE = 'item_passivo';
}
