<?php

declare(strict_types=1);

namespace Jogo\Application\Simulation;

/** Relógio exclusivo de instrumentação; nunca fornece dt ao domínio. */
interface MeasurementClock
{
    /** @phpstan-impure */
    public function nowNanoseconds(): int;
}
