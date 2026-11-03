<?php

declare(strict_types=1);

namespace Jogo\Domain\Ai\Behavior;

use Jogo\Domain\Ai\EntityView;
use Jogo\Domain\Ai\WorldView;
use Jogo\Domain\Value\Vector2;

/** Seek: ir em linha reta na direção do jogador. */
final class SeekBehavior implements BehaviorStrategy
{
    public function direction(EntityView $enemy, WorldView $world, float $deltaSeconds): Vector2
    {
        // Em cima do jogador o resultado é zero: não existe direção útil.
        return $world->playerPosition()->subtract($enemy->position)->normalized();
    }
}
