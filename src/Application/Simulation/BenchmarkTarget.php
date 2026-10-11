<?php

declare(strict_types=1);

namespace Jogo\Application\Simulation;

use Jogo\Domain\Entity\Damageable;
use Jogo\Domain\Entity\Entity;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;

/** Alvo sintético estático, sem arquétipo, IA, spawn ou conteúdo da C. */
final class BenchmarkTarget extends Entity implements Damageable
{
    private readonly Health $health;

    public function __construct(string $id, Vector2 $position)
    {
        parent::__construct($id, $position, 4.0);
        $this->health = new Health(20.0);
    }

    public function health(): Health
    {
        return $this->health;
    }
}
