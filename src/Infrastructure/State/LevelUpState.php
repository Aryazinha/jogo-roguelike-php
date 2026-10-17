<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\State;

final class LevelUpState extends ScreenState
{
    public function name(): string
    {
        return 'level_up';
    }

    public function rendersBelow(): bool
    {
        return true;
    }
}
