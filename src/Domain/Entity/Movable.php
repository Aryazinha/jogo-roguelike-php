<?php

declare(strict_types=1);

namespace Jogo\Domain\Entity;

use Jogo\Domain\Value\Vector2;

/** Capacidade de movimento ratificada na A1, sem atributos de personagem. */
interface Movable
{
    public function position(): Vector2;

    public function moveTo(Vector2 $position): void;

    public function translate(Vector2 $displacement): void;
}
