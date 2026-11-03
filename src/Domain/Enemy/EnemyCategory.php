<?php

declare(strict_types=1);

namespace Jogo\Domain\Enemy;

/** Grupos de enemies.json; o valor é o nome da seção no arquivo. */
enum EnemyCategory: string
{
    case COMMON = 'comuns';
    case SPECIAL = 'especiais';
    case BOSS = 'boss';
}
