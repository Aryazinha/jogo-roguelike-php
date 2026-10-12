<?php

declare(strict_types=1);

namespace Jogo\Application\Benchmark;

use Jogo\Domain\Entity\Damageable;
use Jogo\Domain\Entity\Entity;
use Jogo\Domain\Value\Facing;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;

/** Entidade sintética; não representa um personagem de gameplay da B. */
final class BenchmarkPlayer extends Entity implements Damageable
{
    public readonly Facing $facing;

    private readonly Health $health;

    public function __construct()
    {
        parent::__construct('a10-player', Vector2::zero(), 6.0);
        $this->facing = new Facing(new Vector2(1.0, 0.0));
        $this->health = new Health(1_000_000_000.0);
    }

    public function health(): Health
    {
        return $this->health;
    }
}
