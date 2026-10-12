<?php

declare(strict_types=1);

namespace Jogo\Application\Benchmark;

use Jogo\Domain\Entity\Damageable;
use Jogo\Domain\Entity\Entity;
use Jogo\Domain\Value\Facing;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;

/** Alvo sintético com vida alta; não possui IA, spawn, regeneração ou drops. */
final class BenchmarkEnemy extends Entity implements Damageable
{
    public readonly Facing $facing;

    private readonly Health $health;

    public function __construct(string $id, Vector2 $position)
    {
        parent::__construct($id, $position, 6.0);
        $this->health = new Health(1_000_000_000.0);
        $this->facing = new Facing(new Vector2(1.0, 0.0));
    }

    public function health(): Health
    {
        return $this->health;
    }
}
