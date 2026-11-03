<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Results;

use Jogo\Infrastructure\State\RankingState;
use Jogo\Infrastructure\State\ScreenState;
use Jogo\Infrastructure\State\StateStack;
use Throwable;

abstract class FinishState extends ScreenState
{
    private string $playerName = '';

    private string $message = 'Digite seu nome e selecione SALVAR.';

    private bool $saved = false;

    private bool $saving = false;

    private int $selected = 0;

    public function __construct(
        private readonly ?ResultsScreenContext $ui = null,
        private readonly ?FinalRunView $run = null,
    ) {}

    public function update(float $deltaSeconds, StateStack $states): void
    {
        if ($this->ui === null || $this->run === null) {
            return;
        }

        $event = $this->ui->input->poll();

        if (! $this->saved && $event->text !== '') {
            $this->playerName = mb_substr(
                $this->playerName.$event->text,
                0,
                24,
                'UTF-8'
            );
        }

        if ($event->backspace && ! $this->saved) {
            $length = mb_strlen($this->playerName, 'UTF-8');

            $this->playerName = mb_substr(
                $this->playerName,
                0,
                max(0, $length - 1),
                'UTF-8'
            );
        }

        if ($event->back) {
            $this->ui->menu();

            return;
        }

        if ($event->horizontal !== 0) {
            $this->selected = (
                $this->selected + $event->horizontal + 4
            ) % 4;
        }

        $clicked = self::clickedButton(
            $event->mouseX,
            $event->mouseY,
            $event->click
        );

        if ($clicked !== null) {
            $this->selected = $clicked;
        }

        if (! $event->confirm && $clicked === null) {
            return;
        }

        switch ($this->selected) {
            case 0:
                $this->save();
                break;

            case 1:
                $states->push(new RankingState(null, $this->ui));
                break;

            case 2:
                $this->ui->newGame();
                break;

            case 3:
                $this->ui->menu();
                break;
        }
    }

    public function render(float $alpha, StateStack $states): void
    {
        if ($this->ui === null || $this->run === null) {
            return;
        }

        $this->ui->renderer->drawFinish(
            $this->run,
            $this->playerName,
            $this->message,
            $this->saved,
            $this->selected,
        );
    }

    public function isSaved(): bool
    {
        return $this->saved;
    }

    public function message(): string
    {
        return $this->message;
    }

    private function save(): void
    {
        if (
            $this->ui === null
            || $this->run === null
            || $this->saved
            || $this->saving
        ) {
            return;
        }

        if (trim($this->playerName) === '') {
            $this->message = 'Informe um nome antes de salvar.';

            return;
        }

        $this->saving = true;

        try {
            $outcome = $this->ui->gateway->save(
                $this->run,
                $this->playerName
            );

            $this->message = $outcome->message;

            if ($outcome->success) {
                $this->saved = true;
            }
        } catch (Throwable) {
            $this->message = 'Falha ao salvar. Tente novamente.';
        } finally {
            $this->saving = false;
        }
    }

    private static function clickedButton(
        ?int $x,
        ?int $y,
        bool $click
    ): ?int {
        if (
            ! $click
            || $x === null
            || $y === null
            || $y < 393
            || $y >= 433
        ) {
            return null;
        }

        for ($i = 0; $i < 4; $i++) {
            $left = 22 + $i * 195;

            if ($x >= $left && $x < $left + 180) {
                return $i;
            }
        }

        return null;
    }
}
