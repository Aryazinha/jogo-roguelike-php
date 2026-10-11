<?php

declare(strict_types=1);

namespace Jogo\Domain\Random;

use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;

/** Estado privado por instância; nenhuma semente implícita ou gerador global. */
final class SeededRandomIndex implements RandomIndex
{
    private readonly Randomizer $random;

    public function __construct(int $seed)
    {
        $this->random = new Randomizer(new Mt19937($seed));
    }

    public function nextIndex(int $count): int
    {
        if ($count < 1) {
            throw new InvalidArgumentException('count deve ser positivo.');
        }

        return $this->random->getInt(0, $count - 1);
    }
}
