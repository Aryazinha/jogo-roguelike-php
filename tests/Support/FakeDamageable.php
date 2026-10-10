<?php

declare(strict_types=1);

namespace Jogo\Tests\Support;

use Jogo\Domain\Entity\Damageable;
use Jogo\Domain\Entity\Entity;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;

/** Alvo mínimo, sem tipo de conteúdo, XP ou comportamento concreto. */
final class FakeDamageable extends Entity implements Damageable
{
    public function __construct(string $id, Vector2 $position, float $collisionRadius, private readonly Health $health)
    {
        parent::__construct($id, $position, $collisionRadius);
    }

    public function health(): Health
    {
        return $this->health;
    }
}
