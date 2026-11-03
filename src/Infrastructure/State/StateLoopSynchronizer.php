<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\State;

use Jogo\Core\GameLoop;

final class StateLoopSynchronizer
{
    private bool $pausedByStack = false;

    public function __construct(
        private readonly StateStack $states,
        private readonly GameLoop $loop,
    ) {}

    /** @phpstan-impure */
    public function synchronize(): void
    {
        $this->states->flush();

        $shouldPause = $this->states->shouldPauseGameplay();

        if ($shouldPause && ! $this->pausedByStack) {
            $this->loop->pause();
            $this->pausedByStack = true;

            return;
        }

        if (! $shouldPause && $this->pausedByStack) {
            $this->loop->resume();
            $this->pausedByStack = false;
        }
    }
}
