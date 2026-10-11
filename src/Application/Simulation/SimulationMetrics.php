<?php

declare(strict_types=1);

namespace Jogo\Application\Simulation;

use Jogo\Domain\Pool\PoolSnapshot;
use Jogo\Domain\System\ProjectileCollisionSnapshot;

final readonly class SimulationMetrics
{
    public function __construct(
        public int $steps,
        public float $elapsedSeconds,
        public int $entitiesAtEnd,
        public int $peakEntities,
        public PoolSnapshot $poolAtEnd,
        public PoolSnapshot $poolAfterCleanup,
        public int $acceptedAttacks,
        public int $impacts,
        public int $deaths,
        public ProjectileCollisionSnapshot $collisions,
    ) {}
}
