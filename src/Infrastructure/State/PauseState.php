<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\State;

use Jogo\Infrastructure\Menu\MenuInputEvent;
use Jogo\Infrastructure\Overlay\OverlayFramePresenter;
use Jogo\Infrastructure\Overlay\OverlayNavigation;
use Jogo\Infrastructure\Overlay\OverlayOption;
use Jogo\Infrastructure\Overlay\OverlaySnapshot;

final class PauseState extends ScreenState
{
    public function __construct(
        private readonly ?OverlayFramePresenter $presenter = null,
    ) {}

    public function name(): string
    {
        return 'pause';
    }

    public function rendersBelow(): bool
    {
        return true;
    }

    public function ownsFrame(): bool
    {
        return $this->presenter !== null;
    }

    public function handleInput(
        MenuInputEvent $event,
        StateStack $states
    ): void {
        if (
            $event->confirm
            || $event->back
            || OverlayNavigation::clicked($event, 1) !== null
        ) {
            $states->pop();
        }
    }

    public function render(float $alpha, StateStack $states): void
    {
        $this->presenter?->render(
            new OverlaySnapshot(
                'PAUSA',
                'O tempo da partida esta congelado.',
                [
                    new OverlayOption(
                        'CONTINUAR',
                        'Voltar para a partida',
                        'ENTER ou P para retomar'
                    ),
                ],
                0,
                'Navegue e confirme para continuar.'
            ),
            $alpha
        );
    }
}
