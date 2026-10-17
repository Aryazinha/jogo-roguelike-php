<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\State;

use Jogo\Infrastructure\Results\FinishState;

final class VictoryState extends FinishState
{
    public function name(): string
    {
        return 'victory';
    }

    public function rendersBelow(): bool
    {
        return false;
    }
}
