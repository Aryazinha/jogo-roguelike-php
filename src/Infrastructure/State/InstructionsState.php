<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\State;

use Jogo\Infrastructure\Menu\MenuScreenContext;

final class InstructionsState extends ScreenState
{
    public function __construct(
        private readonly ?MenuScreenContext $ui = null,
    ) {}

    public function name(): string
    {
        return 'instructions';
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
            'INSTRUCOES',
            ['VOLTAR'],
            0,
            [
                'WASD: mover personagem | Setas: olhar',
                'Ataques sao automaticos.',
                'Derrote inimigos e acumule experiencia.',
                'Ao subir de nivel, escolha uma melhoria.',
                'O boss surge apos 10 minutos de sobrevivencia.',
                'P: solicitar pausa durante a partida.',
            ]
        );
    }
}
