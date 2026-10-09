<?php

declare(strict_types=1);

namespace Jogo\Core;

/**
 * Fornece tempo monotônico em segundos, finito e não negativo.
 * A origem é arbitrária; apenas diferenças entre leituras têm significado.
 */
interface ClockInterface
{
    /** @phpstan-impure */
    public function now(): float;
}
