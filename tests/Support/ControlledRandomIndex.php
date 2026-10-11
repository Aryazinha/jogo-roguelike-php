<?php

declare(strict_types=1);

namespace Jogo\Tests\Support;

use Jogo\Domain\Random\RandomIndex;

final class ControlledRandomIndex implements RandomIndex
{
    /** @var list<int> */
    public array $counts = [];

    public function __construct(public int $index = 0) {}

    public function nextIndex(int $count): int
    {
        $this->counts[] = $count;

        return $this->index;
    }
}
