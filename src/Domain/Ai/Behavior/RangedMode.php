<?php

declare(strict_types=1);

namespace Jogo\Domain\Ai\Behavior;

/** As três faixas do inimigo à distância (PLANO.md §4.1, tabela de kiting). */
enum RangedMode: string
{
    case CHASE = 'perseguindo';
    case ORBIT = 'orbitando';
    case FLEE = 'fugindo';
}
