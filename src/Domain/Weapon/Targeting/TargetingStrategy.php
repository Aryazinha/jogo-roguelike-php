<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon\Targeting;

use Jogo\Domain\Ai\WorldView;

interface TargetingStrategy
{
    public function select(TargetingContext $context, WorldView $world): ?TargetingResult;
}
