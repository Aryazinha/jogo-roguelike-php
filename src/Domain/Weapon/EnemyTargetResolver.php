<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon;

interface EnemyTargetResolver
{
    /** @phpstan-impure */
    public function resolve(string $id): ?EnemyTarget;
}
