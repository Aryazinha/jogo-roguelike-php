<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\State;

use Jogo\Infrastructure\Menu\MenuInputEvent;
use Jogo\Infrastructure\Overlay\LevelUpScreenContext;
use Jogo\Infrastructure\Overlay\OverlayFramePresenter;
use Jogo\Infrastructure\Overlay\OverlayNavigation;
use Jogo\Infrastructure\Overlay\OverlayOption;
use Jogo\Infrastructure\Overlay\OverlaySnapshot;
use Throwable;

final class LevelUpState extends ScreenState
{
    private int $selected = 0;

    private ?int $pendingOptionIndex = null;

    private bool $confirmed = false;

    private string $message = '';

    public function __construct(
        private readonly ?LevelUpScreenContext $context = null,
        private readonly ?OverlayFramePresenter $presenter = null,
    ) {}

    public function name(): string
    {
        return 'level_up';
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
        if ($this->context === null || $this->confirmed) {
            return;
        }

        if ($event->back) {
            if ($this->pendingOptionIndex !== null) {
                $this->pendingOptionIndex = null;
                $this->selected = 0;
                $this->message = '';

                return;
            }

            if ($this->context->allowCancel) {
                $states->pop();
            }

            return;
        }

        $count = $this->pendingOptionIndex === null
            ? count($this->context->options)
            : count($this->context->replacementSlots);

        if ($count === 0) {
            return;
        }

        $direction = $event->vertical !== 0
            ? $event->vertical
            : $event->horizontal;

        $this->selected = OverlayNavigation::move(
            $this->selected,
            $direction,
            $count
        );

        $clicked = OverlayNavigation::clicked($event, $count);

        if ($clicked !== null) {
            $this->selected = $clicked;
        }

        if (! $event->confirm && $clicked === null) {
            return;
        }

        if ($this->pendingOptionIndex !== null) {
            $option = $this->context->options[$this->pendingOptionIndex];
            $slot = $this->context->replacementSlots[$this->selected];

            $this->submit($option->id, $slot->id, $states);

            return;
        }

        $option = $this->context->options[$this->selected];

        if ($option->requiresReplacement) {
            if ($this->context->replacementSlots === []) {
                $this->message = 'Nenhum slot disponivel para substituicao.';

                return;
            }

            $this->pendingOptionIndex = $this->selected;
            $this->selected = 0;
            $this->message = '';

            return;
        }

        $this->submit($option->id, null, $states);
    }

    public function render(float $alpha, StateStack $states): void
    {
        if ($this->context === null || $this->presenter === null) {
            return;
        }

        $this->presenter->render($this->overlay(), $alpha);
    }

    public function overlay(): OverlaySnapshot
    {
        if ($this->context === null) {
            return new OverlaySnapshot(
                'LEVEL UP',
                'Aguardando dados da Application.',
                [],
            );
        }

        if ($this->pendingOptionIndex !== null) {
            $options = [];

            foreach ($this->context->replacementSlots as $slot) {
                $options[] = new OverlayOption(
                    'SUBSTITUIR: '.$slot->weaponName,
                    'Slot: '.$slot->id,
                    'A escolha sera enviada para a Application'
                );
            }

            return new OverlaySnapshot(
                'SUBSTITUIR ARMA',
                $this->message !== ''
                    ? $this->message
                    : 'Escolha qual arma sera substituida.',
                $options,
                $this->selected,
                'ESC: voltar as melhorias | ENTER: confirmar'
            );
        }

        $options = [];

        foreach ($this->context->options as $option) {
            $options[] = $option->asOverlayOption();
        }

        $message = $this->message;

        if ($options === []) {
            $message = 'Nenhuma melhoria disponivel. Aguarde a Application.';
        } elseif ($message === '') {
            $message = 'Escolha uma melhoria para continuar.';
        }

        return new OverlaySnapshot(
            'LEVEL UP',
            $message,
            $options,
            $this->selected,
            $this->context->allowCancel
                ? 'ENTER: escolher | ESC: cancelar'
                : 'ENTER: escolher | selecao obrigatoria'
        );
    }

    private function submit(
        string $optionId,
        ?string $slotId,
        StateStack $states
    ): void {
        try {
            $accepted = $this->context?->confirm(
                $optionId,
                $slotId
            ) ?? false;

            if (! $accepted) {
                $this->message = 'Escolha nao aplicada. Tente novamente.';

                return;
            }

            $this->confirmed = true;
            $states->pop();
        } catch (Throwable) {
            $this->message = 'Falha ao aplicar a escolha. Tente novamente.';
        }
    }
}
