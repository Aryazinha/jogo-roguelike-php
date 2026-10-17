<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\State;

use Jogo\Infrastructure\Menu\MenuScreenContext;

final class RankingState extends ScreenState
{
    public function __construct(
        private readonly ?MenuScreenContext $ui = null,
    ) {}

    public function name(): string
    {
        return 'ranking';
    }

    public function update(float $deltaSeconds, StateStack $states): void
    {
        if ($this->ui === null) {
            return;
        }

        $event = $this->ui->input->poll();

        if (
            $event->back
            || $event->confirm
            || MenuScreenContext::clickedOption($event, 1) !== null
        ) {
            $states->pop();
        }
    }

    public function render(float $alpha, StateStack $states): void
    {
        $this->ui?->renderer->draw(
            'RANKING',
            ['VOLTAR'],
            0,
            [
                'A consulta de pontuacoes sera integrada na D10.',
                'Esta tela ja pode ser acessada pelo menu.',
            ]
        );
    }
}
