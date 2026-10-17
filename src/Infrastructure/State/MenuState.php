<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\State;

final class MenuState extends ScreenState
{
    public function name(): string
    {
        return 'menu';
    }

    public function rendersBelow(): bool
    {
        return false;
    }
}
