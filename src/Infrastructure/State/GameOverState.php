<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\State;

final class GameOverState extends ScreenState
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
