<?php

declare(strict_types=1);

namespace Jogo\Domain\Ai;

use Jogo\Domain\Value\Vector2;

interface WorldView
{
    public function playerPosition(): Vector2;

    /** @return list<EntityView> Entidades ativas por distância entre centros, em ordem por ID. */
    public function neighbors(Vector2 $center, float $radius): array;
}
