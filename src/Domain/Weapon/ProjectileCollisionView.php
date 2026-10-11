<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon;

use Jogo\Domain\Ai\EntityView;
use Jogo\Domain\Value\Vector2;

/** Consulta de leitura separada da semântica por centros de WorldView. */
interface ProjectileCollisionView
{
    /** @return list<EntityView> Candidatos cujos círculos podem interceptar o segmento. */
    public function candidates(Vector2 $start, Vector2 $end): array;
}
