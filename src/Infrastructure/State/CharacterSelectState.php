<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\State;

final class CharacterSelectState extends ScreenState
{
    public function name(): string
    {
        return 'character_select';
    }

    public function rendersBelow(): bool
    {
        return false;
    }
}
