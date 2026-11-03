<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Play;

use InvalidArgumentException;
use Jogo\Domain\Value\Vector2;

final readonly class PlayEntityView
{
    public const KINDS = [
        'map',
        'scenery',
        'enemy',
        'special_enemy',
        'boss',
        'projectile',
        'tnt',
        'sword',
        'pulse',
        'chest',
        'pickup',
        'breakable',
        'effect',
    ];

    public function __construct(
        public string $kind,
        public Vector2 $previous,
        public Vector2 $current,
        public float $radius,
    ) {
        if (! in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException('Tipo visual desconhecido.');
        }

        if (! is_finite($radius) || $radius <= 0.0) {
            throw new InvalidArgumentException('Raio visual invalido.');
        }
    }
}
