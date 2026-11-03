<?php

declare(strict_types=1);

namespace Jogo\Domain\RandomEvent;

use InvalidArgumentException;

/**
 * Um efeito de evento em events.json, sempre em PERCENT: 2.0 quer dizer +200%
 * (o valor fica 3 vezes maior) e -0.5 quer dizer -50% (fica pela metade).
 */
final readonly class EventEffect
{
    public const SPAWN_COUNT = 'inimigosPorSpawn';

    public const SPAWN_INTERVAL = 'intervaloSpawnSegundos';

    public const XP_DROP = 'xpDropado';

    public const CHEST_CHANCE = 'chanceDeBau';

    public const ATTRIBUTES = [self::SPAWN_COUNT, self::SPAWN_INTERVAL, self::XP_DROP, self::CHEST_CHANCE];

    public function __construct(public string $attribute, public float $percent)
    {
        if (! in_array($attribute, self::ATTRIBUTES, true)) {
            throw new InvalidArgumentException("atributo de evento desconhecido: {$attribute}.");
        }
        // -100% zeraria o valor: um intervalo zero geraria inimigos sem fim.
        if (! is_finite($percent) || $percent <= -1.0) {
            throw new InvalidArgumentException('percent deve ser finito e maior que -1.');
        }
    }
}
