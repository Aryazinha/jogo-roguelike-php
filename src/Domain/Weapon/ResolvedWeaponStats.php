<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon;

use Jogo\Domain\Value\StatModifier;
use Jogo\Domain\Value\StatResolver;
use Jogo\Domain\Value\Stats;

final readonly class ResolvedWeaponStats
{
    public Stats $stats;

    public WeaponDefinition $definition;

    /** @param list<StatModifier> $modifiers */
    public function __construct(WeaponDefinition $base, array $modifiers)
    {
        $this->stats = (new StatResolver)->resolve(WeaponStatsMapper::fromDefinition($base), $modifiers);
        $this->definition = WeaponStatsMapper::toDefinition($base, $this->stats);
    }
}
