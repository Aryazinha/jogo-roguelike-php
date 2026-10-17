<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\State;

final class PauseState extends ScreenState
{
    public function name(): string
    {
        return 'pause';
    }

    public function rendersBelow(): bool
    {
        return true;
    }
}
