<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Play;

use InvalidArgumentException;

final readonly class BossHudSnapshot
{
    public function __construct(
        public string $name,
        public float $health,
        public float $maxHealth,
    ) {
        if (
            trim($name) === ''
            || ! is_finite($health)
            || ! is_finite($maxHealth)
            || $maxHealth <= 0.0
            || $health < 0.0
            || $health > $maxHealth
        ) {
            throw new InvalidArgumentException('Dados do boss invalidos.');
        }
    }

    public function healthRatio(): float
    {
        return $this->health / $this->maxHealth;
    }
}
