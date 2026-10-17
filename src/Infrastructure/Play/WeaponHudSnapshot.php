<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Play;

use InvalidArgumentException;

final readonly class WeaponHudSnapshot
{
    public function __construct(
        public string $name,
        public int $level,
        public float $cooldownRemaining,
    ) {
        if (
            trim($name) === ''
            || $level < 1
            || ! is_finite($cooldownRemaining)
            || $cooldownRemaining < 0.0
        ) {
            throw new InvalidArgumentException('Dados de arma invalidos.');
        }
    }
}
