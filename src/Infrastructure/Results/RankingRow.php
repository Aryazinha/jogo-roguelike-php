<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Results;

use InvalidArgumentException;

final readonly class RankingRow
{
    public function __construct(
        public string $name,
        public string $character,
        public int $score,
        public int $level,
        public int $kills,
        public float $elapsedSeconds,
    ) {
        if (
            trim($name) === ''
            || trim($character) === ''
            || $score < 0
            || $level < 1
            || $kills < 0
            || ! is_finite($elapsedSeconds)
            || $elapsedSeconds < 0.0
        ) {
            throw new InvalidArgumentException(
                'Linha de ranking invalida.'
            );
        }
    }
}
