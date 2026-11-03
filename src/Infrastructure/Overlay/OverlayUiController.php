<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Overlay;

use Jogo\Infrastructure\Menu\MenuInputInterface;
use Jogo\Infrastructure\State\LevelUpState;
use Jogo\Infrastructure\State\PauseState;
use Jogo\Infrastructure\State\StateLoopSynchronizer;
use Jogo\Infrastructure\State\StateStack;

final readonly class OverlayUiController
{
    public function __construct(
        private StateStack $states,
        private MenuInputInterface $input,
        private StateLoopSynchronizer $synchronizer,
    ) {}

    /**
     * Chamar uma vez por quadro, antes do GameLoop::tick().
     *
     * @phpstan-impure
     */
    public function process(): void
    {
        $this->synchronizer->synchronize();

        $top = $this->states->top();

        if (
            $top instanceof LevelUpState
            || $top instanceof PauseState
        ) {
            $event = $this->input->poll();

            $top->handleInput($event, $this->states);
        }

        $this->synchronizer->synchronize();
    }
}
