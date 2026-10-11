<?php

declare(strict_types=1);

namespace Jogo\Application\Simulation;

final class MonotonicMeasurement implements MeasurementClock
{
    public function nowNanoseconds(): int
    {
        return hrtime(true);
    }
}
