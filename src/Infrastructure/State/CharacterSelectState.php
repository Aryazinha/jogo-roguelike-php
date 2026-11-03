<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\State;

use Jogo\Infrastructure\Menu\MenuScreenContext;

final class CharacterSelectState extends ScreenState
{
    private int $selected = 0;

    public function __construct(
        private readonly ?MenuScreenContext $ui = null,
    ) {}

    public function name(): string
    {
        return 'character_select';
    }

    public function update(float $deltaSeconds, StateStack $states): void
    {
        if ($this->ui === null) {
            return;
        }

        $event = $this->ui->input->poll();

        if ($event->back) {
            $states->pop();

            return;
        }

        $count = count($this->ui->characters);

        $direction = $event->vertical !== 0
            ? $event->vertical
            : $event->horizontal;

        $this->selected = MenuScreenContext::moveSelection(
            $this->selected,
            $direction,
            $count
        );

        $clicked = MenuScreenContext::clickedOption(
            $event,
            $count
        );

        if ($clicked !== null) {
            $this->selected = $clicked;
        }

        if ($event->confirm || $clicked !== null) {
            $choice = $this->ui->characters[$this->selected];

            // A Application recebera somente o ID.
            $this->ui->selectCharacter($choice->id);
        }
    }

    public function render(float $alpha, StateStack $states): void
    {
        if ($this->ui === null) {
            return;
        }

        $choice = $this->ui->characters[$this->selected];

        $names = array_map(
            static fn ($character): string => $character->name,
            $this->ui->characters
        );

        $this->ui->renderer->draw(
            'ESCOLHA SEU PERSONAGEM',
            $names,
            $this->selected,
            [
                'Vida: '.$choice->health.' HP | Regeneracao: 1 HP/'.$choice->regenerationSeconds.'s',
                'Arma inicial: '.$choice->startingWeapon,
                'Passiva: '.$choice->passiveDescription,
            ]
        );
    }
}
