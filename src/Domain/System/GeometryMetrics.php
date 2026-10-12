<?php

declare(strict_types=1);

namespace Jogo\Domain\System;

use InvalidArgumentException;

/** Contador opcional por passo; não conhece relatório, relógio ou entidades de conteúdo. */
final class GeometryMetrics
{
    private int $candidates = 0;

    private int $circles = 0;

    private int $segments = 0;

    private int $sectors = 0;

    private int $rebuilds = 0;

    public function beginStep(): void
    {
        $this->candidates = $this->circles = $this->segments = $this->sectors = $this->rebuilds = 0;
    }

    public function recordCandidates(int $count): void
    {
        if ($count < 0) {
            throw new InvalidArgumentException('A quantidade de candidatos não pode ser negativa.');
        }
        $this->candidates += $count;
    }

    public function recordCircle(): void
    {
        $this->circles++;
    }

    public function recordSegment(): void
    {
        $this->segments++;
    }

    public function recordSector(): void
    {
        $this->sectors++;
    }

    public function recordRebuild(): void
    {
        $this->rebuilds++;
    }

    public function snapshot(): GeometrySnapshot
    {
        return new GeometrySnapshot($this->candidates, $this->circles, $this->segments, $this->sectors, $this->rebuilds);
    }
}
