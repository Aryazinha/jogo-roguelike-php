<?php

declare(strict_types=1);

namespace Jogo\Domain\System;

use InvalidArgumentException;

final readonly class GeometrySnapshot
{
    public function __construct(public int $candidates, public int $circles, public int $segments,
        public int $sectors, public int $rebuilds)
    {
        if (min($candidates, $circles, $segments, $sectors, $rebuilds) < 0) {
            throw new InvalidArgumentException('Contadores geométricos não podem ser negativos.');
        }
    }

    public function narrowTests(): int
    {
        return $this->circles + $this->segments + $this->sectors;
    }
}
