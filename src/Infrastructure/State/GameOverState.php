<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\State;

use Jogo\Infrastructure\Results\FinishState;

final class GameOverState extends FinishState
{
    public function name(): string
    {
        return 'game_over';
    }

    public function rendersBelow(): bool
    {
        return false;
    }
}
