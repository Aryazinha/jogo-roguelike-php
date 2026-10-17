<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\State;

use Jogo\Infrastructure\Menu\MenuScreenContext;
use Jogo\Infrastructure\Results\RankingPage;
use Jogo\Infrastructure\Results\ResultsScreenContext;
use Throwable;

final class RankingState extends ScreenState
{
    private const PAGE_SIZE = 6;

    private int $offset = 0;

    private string $message = '';

    private RankingPage $page;

    public function __construct(
        private readonly ?MenuScreenContext $ui = null,
        private readonly ?ResultsScreenContext $results = null,
    ) {
        $this->page = new RankingPage([], false);
    }

    public function name(): string
    {
        return 'ranking';
    }

    public function enter(StateStack $states): void
    {
        if ($this->results !== null) {
            $this->loadPage();
        }
    }

    public function update(float $deltaSeconds, StateStack $states): void
    {
        if ($this->results !== null) {
            $this->updateRealRanking($states);

            return;
        }

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
        if ($this->results !== null) {
            $this->results->renderer->drawRanking(
                $this->page,
                $this->offset,
                $this->message
            );

            return;
        }

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

    private function updateRealRanking(StateStack $states): void
    {
        if ($this->results === null) {
            return;
        }

        $event = $this->results->input->poll();

        if ($event->back) {
            $states->pop();

            return;
        }

        $action = $event->horizontal;

        if (
            $event->click
            && $event->mouseX !== null
            && $event->mouseY !== null
            && $event->mouseY >= 393
            && $event->mouseY < 433
        ) {
            $x = $event->mouseX;

            if ($x >= 80 && $x < 250) {
                $action = -1;
            } elseif ($x >= 315 && $x < 485) {
                $action = 1;
            } elseif ($x >= 550 && $x < 720) {
                $states->pop();

                return;
            }
        }

        if ($action < 0 && $this->offset > 0) {
            $this->offset = max(
                0,
                $this->offset - self::PAGE_SIZE
            );

            $this->loadPage();
        }

        if ($action > 0 && $this->page->hasNext) {
            $this->offset += self::PAGE_SIZE;
            $this->loadPage();
        }

        if ($event->confirm) {
            $this->loadPage();
        }
    }

    private function loadPage(): void
    {
        if ($this->results === null) {
            return;
        }

        try {
            $this->page = $this->results->gateway->page(
                $this->offset,
                self::PAGE_SIZE
            );

            $this->message = $this->page->rows === []
                ? 'Nenhuma pontuacao nesta pagina.'
                : '';
        } catch (Throwable) {
            $this->page = new RankingPage([], false);
            $this->message = 'Falha ao consultar ranking.';
        }
    }
}
