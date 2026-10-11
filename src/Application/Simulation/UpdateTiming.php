<?php

declare(strict_types=1);

namespace Jogo\Application\Simulation;

final readonly class UpdateTiming
{
    public function __construct(public int $samples, public int $totalNanoseconds, public int $maxNanoseconds) {}

    public function averageNanoseconds(): float
    {
        return $this->samples === 0 ? 0.0 : $this->totalNanoseconds / $this->samples;
    }
}
