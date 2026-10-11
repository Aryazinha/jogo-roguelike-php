<?php

declare(strict_types=1);

namespace Jogo\Tests\Support;

final class PoolItem
{
    public int $value = 0;

    public ?object $reference = null;

    public function reset(): void
    {
        $this->value = 0;
        $this->reference = null;
    }
}
