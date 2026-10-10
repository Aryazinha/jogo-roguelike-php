<?php

declare(strict_types=1);

namespace Jogo\Tests\Support;

use Jogo\Domain\Weapon\WeaponDefinition;

/** Definições mínimas de teste; o identificador pulso não executa qualquer efeito. */
final class WeaponFixtures
{
    public static function definition(string $id = 'test', float $cooldownSeconds = 2.0): WeaponDefinition
    {
        return new WeaponDefinition($id, 'Test weapon', 2.5, $cooldownSeconds, 'area_ao_redor', 'pulso', 70.0);
    }
}
