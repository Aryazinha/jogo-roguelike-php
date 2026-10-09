<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Time;

use Jogo\Core\ClockInterface;

/**
 * Relógio de produção independente de ajustes na data e hora do sistema.
 */
final class MonotonicClock implements ClockInterface
{
    /** @phpstan-impure */
    public function now(): float
    {
        return hrtime(true) / 1_000_000_000;
    }
}
