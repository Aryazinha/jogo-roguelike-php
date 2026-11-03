<?php

declare(strict_types=1);

namespace Jogo\Domain\Ai\Behavior;

use Jogo\Domain\Ai\EntityView;
use Jogo\Domain\Ai\WorldView;
use Jogo\Domain\Value\Vector2;

/**
 * Decide para onde um inimigo quer ir neste passo (contrato §8 de CONTRATOS.md).
 *
 * Só lê o mundo e devolve um vetor de comprimento entre 0 e 1: 1 é andar na
 * velocidade máxima, menos que 1 é andar mais devagar, zero é ficar parado.
 * O §8 previa só "normalizada ou zero"; a intensidade parcial é a mesma que
 * o MovementSystem da A já aceita para entrada analógica. Quem aplica
 * velocidade e deslocamento continua sendo a Frente A.
 */
interface BehaviorStrategy
{
    public function direction(EntityView $enemy, WorldView $world, float $deltaSeconds): Vector2;
}
