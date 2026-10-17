<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\State;

final class InstructionsState extends ScreenState
{
    public function name(): string
    {
        return 'instructions';
    }

    public function rendersBelow(): bool
    {
        return false;
    }
}
