<?php

declare(strict_types=1);

namespace Jogo\Application\Simulation;

use Jogo\Core\ClockInterface;
use Jogo\Domain\Value\CombatValidation;

final class SimulationClock implements ClockInterface
{
    private float $seconds = 0.0;

    public function advance(float $seconds): void
    {
        CombatValidation::nonNegative($seconds, 'seconds');
        $next = $this->seconds + $seconds;
        CombatValidation::nonNegative($next, 'timestamp');
        $this->seconds = $next;
    }

    /** @phpstan-pure */
    public function now(): float
    {
        return $this->seconds;
    }
}
