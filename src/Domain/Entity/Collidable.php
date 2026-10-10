<?php

declare(strict_types=1);

namespace Jogo\Domain\Entity;

use Jogo\Domain\Value\Vector2;

/** Capacidade circular ratificada na A1; não implica vida ou dano. */
interface Collidable
{
    public function id(): string;

    public function position(): Vector2;

    public function collisionRadius(): float;

    public function isActive(): bool;
}
