<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\State;

final class RankingState extends ScreenState
{
    public function name(): string
    {
        return 'ranking';
    }

    public function rendersBelow(): bool
    {
        return false;
    }
}
