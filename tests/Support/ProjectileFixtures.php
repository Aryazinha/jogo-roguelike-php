<?php

declare(strict_types=1);

namespace Jogo\Tests\Support;

use Jogo\Domain\Weapon\WeaponDefinition;

final class ProjectileFixtures
{
    public static function bow(string $id = 'test-bow', int $count = 1, float $cooldown = 0.01, float $speed = 420.0, float $range = 320.0): WeaponDefinition
    {
        return new WeaponDefinition($id, 'Synthetic bow', 3.2, $cooldown, 'inimigo_mais_proximo', 'projetil', $range,
            projectileCount: $count, projectileSpeed: $speed);
    }
}
