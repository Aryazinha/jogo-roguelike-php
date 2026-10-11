<?php

declare(strict_types=1);

namespace Jogo\Domain\Random;

interface RandomIndex
{
    /** @phpstan-impure */
    public function nextIndex(int $count): int;
}
