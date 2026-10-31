<?php

declare(strict_types=1);

namespace Jogo\Domain\Scenery;

use Jogo\Domain\Entity\Damageable;
use Jogo\Domain\Entity\Entity;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;

/** Elemento de cenário que quebra com dano e pode deixar um item (RF38). */
final class Breakable extends Entity implements Damageable
{
    private readonly Health $health;

    public function __construct(string $id, private readonly BreakableType $type, Vector2 $position)
    {
        parent::__construct($id, $position, $type->collisionRadius);
        $this->health = new Health($type->maxHealth);
    }

    public function type(): BreakableType
    {
        return $this->type;
    }

    public function health(): Health
    {
        return $this->health;
    }

    public function blocksMovement(): bool
    {
        return $this->type->blocksMovement;
    }
}
