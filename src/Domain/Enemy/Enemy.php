<?php

declare(strict_types=1);

namespace Jogo\Domain\Enemy;

use Jogo\Domain\Entity\Damageable;
use Jogo\Domain\Entity\Entity;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\EnemyTarget;

/**
 * Inimigo real da partida: posição e vida próprias, dados fixos no arquétipo.
 *
 * Movimento, colisão e dano continuam com os sistemas da Frente A; esta
 * classe só compõe as capacidades que eles esperam.
 */
final class Enemy extends Entity implements Damageable
{
    private readonly Health $health;

    public function __construct(string $id, private readonly EnemyArchetype $archetype, Vector2 $position)
    {
        parent::__construct($id, $position, $archetype->collisionRadius);
        $this->health = new Health($archetype->maxHealth);
    }

    public function archetype(): EnemyArchetype
    {
        return $this->archetype;
    }

    public function typeId(): string
    {
        return $this->archetype->id;
    }

    public function isBoss(): bool
    {
        return $this->archetype->isBoss();
    }

    public function health(): Health
    {
        return $this->health;
    }

    public function contactDamage(): float
    {
        return $this->archetype->damage;
    }

    public function speed(): float
    {
        return $this->archetype->speed;
    }

    /** Metadados que o DamageSystem precisa para publicar EnemyHit/EnemyKilled. */
    public function asTarget(): EnemyTarget
    {
        return new EnemyTarget($this, $this->typeId(), $this->isBoss());
    }
}
