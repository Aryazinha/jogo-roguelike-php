<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon\Targeting;

use Jogo\Domain\Ai\WorldView;
use Jogo\Domain\Value\Vector2;

final class SurroundingTargeting implements TargetingStrategy
{
    public function select(TargetingContext $context, WorldView $world): TargetingResult
    {
        return new TargetingResult(Vector2::zero(), $context->origin, null);
    }
}
