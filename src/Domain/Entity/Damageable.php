<?php

declare(strict_types=1);

namespace Jogo\Domain\Entity;

use Jogo\Domain\Value\Health;

/** Vida por composição, conforme o contrato ratificado na A1. */
interface Damageable extends Collidable
{
    public function health(): Health;
}
