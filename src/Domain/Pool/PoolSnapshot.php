<?php

declare(strict_types=1);

namespace Jogo\Domain\Pool;

final readonly class PoolSnapshot
{
    public function __construct(
        public int $capacity,
        public int $active,
        public int $available,
        public int $peakActive,
        public int $acquisitions,
        public int $releases,
        public int $refusedBatches,
    ) {}
}
