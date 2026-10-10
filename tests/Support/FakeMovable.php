<?php

declare(strict_types=1);

namespace Jogo\Tests\Support;

use Jogo\Domain\Entity\Movable;
use Jogo\Domain\Value\Vector2;

/** Objeto mínimo para provar que o sistema não exige uma entidade concreta. */
final class FakeMovable implements Movable
{
    public function __construct(private Vector2 $position) {}

    public function position(): Vector2
    {
        return $this->position;
    }

    public function moveTo(Vector2 $position): void
    {
        $this->position = $position;
    }

    public function translate(Vector2 $displacement): void
    {
        $this->position = $this->position->add($displacement);
    }
}
