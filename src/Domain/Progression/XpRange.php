<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression;

use InvalidArgumentException;
use Jogo\Domain\Random\RandomIndex;

/** Faixa inclusiva de XP de um tipo de inimigo (RF11), sorteada com RNG injetado. */
final readonly class XpRange
{
    public function __construct(public int $min, public int $max)
    {
        if ($min < 0 || $max < $min) {
            throw new InvalidArgumentException('A faixa de XP exige 0 <= min <= max.');
        }
    }

    public function roll(RandomIndex $random): int
    {
        return $this->min + $random->nextIndex($this->max - $this->min + 1);
    }
}
