<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon\Targeting;

use Jogo\Domain\Ai\WorldView;

final class FacingTargeting implements TargetingStrategy
{
    public function select(TargetingContext $context, WorldView $world): TargetingResult
    {
        return new TargetingResult($context->facing,
            $context->origin->add($context->facing->scale($context->range)), null);
    }
}
