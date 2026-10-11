<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon;

use Jogo\Domain\Weapon\Targeting\TargetingContext;

interface AttackContextProvider
{
    /** @phpstan-impure */
    public function context(float $range): TargetingContext;
}
