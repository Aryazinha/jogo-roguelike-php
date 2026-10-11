<?php

declare(strict_types=1);

namespace Jogo\Tests\Support;

use Jogo\Application\Simulation\MeasurementClock;

final class FixedMeasurement implements MeasurementClock
{
    private int $time = 0;

    public function __construct(private readonly int $interval = 1000) {}

    public function nowNanoseconds(): int
    {
        $this->time += $this->interval;

        return $this->time;
    }
}
