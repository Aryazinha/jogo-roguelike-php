<?php

declare(strict_types=1);

namespace Jogo\Domain\Ai\Behavior;

use Jogo\Domain\Ai\EntityView;
use Jogo\Domain\Ai\WorldView;
use Jogo\Domain\Value\Vector2;

/**
 * Decide para onde um inimigo quer ir neste passo (contrato §8 de CONTRATOS.md).
 *
 * Só lê o mundo: devolve uma direção normalizada ou o vetor zero, e quem
 * aplica velocidade e deslocamento é o MovementSystem da Frente A.
 */
interface BehaviorStrategy
{
    public function direction(EntityView $enemy, WorldView $world, float $deltaSeconds): Vector2;
}
