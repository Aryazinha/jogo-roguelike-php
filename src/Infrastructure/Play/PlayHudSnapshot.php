<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Play;

use InvalidArgumentException;

final readonly class PlayHudSnapshot
{
    /**
     * @param  list<WeaponHudSnapshot>  $weapons
     * @param  list<string>  $passives
     */
    public function __construct(
        public float $health,
        public float $maxHealth,
        public int $xp,
        public int $xpToNextLevel,
        public int $level,
        public float $elapsedSeconds,
        public array $weapons = [],
        public array $passives = [],
        public ?BossHudSnapshot $boss = null,
        public ?string $wave = null,
    ) {
        if (
            ! is_finite($health)
            || ! is_finite($maxHealth)
            || ! is_finite($elapsedSeconds)
            || $maxHealth <= 0.0
            || $health < 0.0
            || $health > $maxHealth
            || $xp < 0
            || $xpToNextLevel < 1
            || $level < 1
            || $elapsedSeconds < 0.0
        ) {
            throw new InvalidArgumentException('Dados do HUD invalidos.');
        }
    }

    public function healthRatio(): float
    {
        return $this->health / $this->maxHealth;
    }

    public function xpRatio(): float
    {
        return min(1.0, $this->xp / $this->xpToNextLevel);
    }

    public function formattedTime(): string
    {
        $seconds = (int) $this->elapsedSeconds;

        return sprintf(
            '%02d:%02d',
            intdiv($seconds, 60),
            $seconds % 60
        );
    }

    /** @return list<string> */
    public function weaponLines(): array
    {
        $lines = [];

        foreach ($this->weapons as $weapon) {
            $lines[] = sprintf(
                '%s Nv.%d CD %.1fs',
                $weapon->name,
                $weapon->level,
                $weapon->cooldownRemaining
            );
        }

        return $lines;
    }
}
