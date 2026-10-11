<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon;

use InvalidArgumentException;
use Jogo\Domain\Value\Stats;

/** Única conversão entre os campos da definição e os nomes canônicos da A1. */
final class WeaponStatsMapper
{
    public const FIELDS = ['dano' => 'damage', 'cooldown' => 'cooldownSeconds', 'alcance' => 'range',
        'projeteis' => 'projectileCount', 'velocidadeProjetil' => 'projectileSpeed',
        'anguloGraus' => 'angleDegrees', 'raioExplosao' => 'explosionRadius',
        'atrasoDetonacaoSegundos' => 'detonationDelaySeconds'];

    public static function fromDefinition(WeaponDefinition $definition): Stats
    {
        $values = [];
        foreach (self::FIELDS as $attribute => $field) {
            $value = $definition->{$field};
            if ($value !== null) {
                $values[$attribute] = (float) $value;
            }
        }

        return new Stats($values);
    }

    public static function toDefinition(WeaponDefinition $base, Stats $stats): WeaponDefinition
    {
        $expected = array_keys(self::fromDefinition($base)->values());
        $actual = array_keys($stats->values());
        sort($expected);
        sort($actual);
        if ($expected !== $actual) {
            throw new InvalidArgumentException('Os atributos devem corresponder exatamente ao formato da arma.');
        }

        return new WeaponDefinition($base->id, $base->name, $stats->value('dano'), $stats->value('cooldown'),
            $base->targetingId, $base->shapeId, $stats->value('alcance'),
            angleDegrees: $stats->has('anguloGraus') ? $stats->value('anguloGraus') : null,
            projectileCount: $stats->has('projeteis') ? (int) $stats->value('projeteis') : null,
            projectileSpeed: $stats->has('velocidadeProjetil') ? $stats->value('velocidadeProjetil') : null,
            explosionRadius: $stats->has('raioExplosao') ? $stats->value('raioExplosao') : null,
            detonationDelaySeconds: $stats->has('atrasoDetonacaoSegundos') ? $stats->value('atrasoDetonacaoSegundos') : null);
    }
}
