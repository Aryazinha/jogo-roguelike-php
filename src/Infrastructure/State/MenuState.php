<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\State;

use Jogo\Infrastructure\Menu\MenuScreenContext;

final class MenuState extends ScreenState
{
    private int $selected = 0;

    public function __construct(
        private readonly ?MenuScreenContext $ui = null,
    ) {}

    public function name(): string
    {
        return 'menu';
    }

    public function update(float $deltaSeconds, StateStack $states): void
    {
        if ($this->ui === null) {
            return;
        }

        $event = $this->ui->input->poll();

        $this->selected = MenuScreenContext::moveSelection(
            $this->selected,
            $event->vertical,
            4
        );

        $clicked = MenuScreenContext::clickedOption($event, 4);

        if ($clicked !== null) {
            $this->selected = $clicked;
        }

        if ($event->back) {
            $this->ui->requestExit();

            return;
        }

        if (! $event->confirm && $clicked === null) {
            return;
        }

        switch ($this->selected) {
            case 0:
                $states->push(new CharacterSelectState($this->ui));
                break;

            case 1:
                $states->push(new RankingState($this->ui));
                break;

            case 2:
                $states->push(new InstructionsState($this->ui));
                break;

            case 3:
                $this->ui->requestExit();
                break;
        }
    }

    public function render(float $alpha, StateStack $states): void
    {
        $this->ui?->renderer->draw(
            'ROGUELIKE',
            [
                'INICIAR',
                'RANKING',
                'INSTRUCOES',
                'SAIR',
            ],
            $this->selected,
            [
                'Sobreviva, evolua e enfrente o boss.',
            ]
        );
    }
}
