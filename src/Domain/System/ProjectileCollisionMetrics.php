<?php

declare(strict_types=1);

namespace Jogo\Domain\System;

/** Conta apenas trajetórias; consultas de mira e snapshots administrativos ficam fora. */
final class ProjectileCollisionMetrics
{
    private int $candidates = 0;

    private int $tests = 0;

    private int $stepCandidates = 0;

    private int $stepTests = 0;

    private int $maxCandidates = 0;

    private int $maxTests = 0;

    public function beginStep(): void
    {
        $this->stepCandidates = 0;
        $this->stepTests = 0;
    }

    public function recordCandidates(int $count): void
    {
        $this->stepCandidates += $count;
        $this->candidates += $count;
    }

    public function recordNarrowTest(): void
    {
        $this->stepTests++;
        $this->tests++;
    }

    public function endStep(): void
    {
        $this->maxCandidates = max($this->maxCandidates, $this->stepCandidates);
        $this->maxTests = max($this->maxTests, $this->stepTests);
    }

    public function snapshot(): ProjectileCollisionSnapshot
    {
        return new ProjectileCollisionSnapshot($this->candidates, $this->tests, $this->stepCandidates,
            $this->stepTests, $this->maxCandidates, $this->maxTests);
    }
}
