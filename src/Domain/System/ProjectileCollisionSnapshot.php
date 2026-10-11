<?php

declare(strict_types=1);

namespace Jogo\Domain\System;

final readonly class ProjectileCollisionSnapshot
{
    public function __construct(
        public int $totalCandidates,
        public int $totalNarrowTests,
        public int $lastStepCandidates,
        public int $lastStepNarrowTests,
        public int $maxStepCandidates,
        public int $maxStepNarrowTests,
    ) {}
}
