<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Results;

use InvalidArgumentException;

final readonly class FinalRunView
{
    public function __construct(
        public string $runId,
        public string $character,
        public int $level,
        public int $kills,
        public float $elapsedSeconds,
        public int $score,
        public bool $victory,
    ) {
        if (
            trim($runId) === ''
            || trim($character) === ''
            || $level < 1
            || $kills < 0
            || ! is_finite($elapsedSeconds)
            || $elapsedSeconds < 0.0
            || $score < 0
        ) {
            throw new InvalidArgumentException(
                'Resumo final da partida invalido.'
            );
        }
    }

    public function formattedTime(): string
    {
        $seconds = (int) $this->elapsedSeconds;

        return sprintf(
            '%02d:%02d',
            intdiv($seconds, 60),
            $seconds % 60
        );
    }
}
