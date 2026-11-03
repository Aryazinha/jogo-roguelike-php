<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Results;

use Closure;

final readonly class ResultsScreenContext
{
    /** @var Closure(): void */
    private Closure $onNewGame;

    /** @var Closure(): void */
    private Closure $onMenu;

    /**
     * @param  callable(): void  $onNewGame
     * @param  callable(): void  $onMenu
     */
    public function __construct(
        public ResultsInputInterface $input,
        public ResultsScreenRendererInterface $renderer,
        public ResultsGatewayInterface $gateway,
        callable $onNewGame,
        callable $onMenu,
    ) {
        $this->onNewGame = Closure::fromCallable($onNewGame);
        $this->onMenu = Closure::fromCallable($onMenu);
    }

    public function newGame(): void
    {
        ($this->onNewGame)();
    }

    public function menu(): void
    {
        ($this->onMenu)();
    }
}
