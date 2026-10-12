<?php

declare(strict_types=1);

namespace Jogo\Application\Benchmark;

use Jogo\Domain\Pool\PoolSnapshot;

final readonly class BenchmarkLogical
{
    public function __construct(public int $warmupSteps, public int $measuredSteps, public int $minimumEnemies,
        public int $maximumEnemies, public int $minimumActiveEntities, public int $measuredProjectilePeak,
        public int $acceptedAttacks, public int $impacts, public int $deaths, public int $contacts,
        public PoolSnapshot $poolAtEnd, public PoolSnapshot $poolAfterCleanup) {}
}
