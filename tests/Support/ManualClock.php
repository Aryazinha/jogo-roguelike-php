<?php

declare(strict_types=1);

namespace Jogo\Tests\Support;

use Jogo\Core\ClockInterface;

/**
 * Relógio controlado pelo teste, sem espera real; aceita dados inválidos para testar a fronteira.
 */
final class ManualClock implements ClockInterface
{
    public function __construct(private float $timeSeconds = 0.0) {}

    /** @phpstan-pure */
    public function now(): float
    {
        return $this->timeSeconds;
    }

    public function advance(float $seconds): void
    {
        $this->timeSeconds += $seconds;
    }

    public function set(float $seconds): void
    {
        $this->timeSeconds = $seconds;
    }
}
