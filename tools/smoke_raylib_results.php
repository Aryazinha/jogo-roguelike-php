<?php

declare(strict_types=1);

use Jogo\Infrastructure\Ffi\RaylibBinding;
use Jogo\Infrastructure\Menu\CharacterChoiceCatalog;
use Jogo\Infrastructure\Menu\MenuScreenContext;
use Jogo\Infrastructure\Menu\RaylibMenuInput;
use Jogo\Infrastructure\Menu\RaylibMenuScreenRenderer;
use Jogo\Infrastructure\Results\FinalRunView;
use Jogo\Infrastructure\Results\RaylibResultsInput;
use Jogo\Infrastructure\Results\RaylibResultsRenderer;
use Jogo\Infrastructure\Results\ResultsScreenContext;
use Jogo\Infrastructure\State\CharacterSelectState;
use Jogo\Infrastructure\State\GameOverState;
use Jogo\Infrastructure\State\MenuState;
use Jogo\Infrastructure\State\StateStack;
use Jogo\Infrastructure\State\VictoryState;
use Jogo\Tools\DemoResultsSqlite;

require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/demo_results_sqlite.php';

$database = sys_get_temp_dir()
    .'/jogo-roguelike-d10-demo.sqlite';

$gateway = new DemoResultsSqlite($database);

$raylib = new RaylibBinding;
$raylib->openWindow(800, 450, 'D10 - Game Over, Vitoria e Ranking');
$raylib->setTargetFps(60);

// ESC volta as telas sem fechar automaticamente a janela.
$raylib->setExitKey(0);

$states = new StateStack;
$running = true;
$request = '';
$chosenCharacter = 'Steve';

try {
    $resultsContext = new ResultsScreenContext(
        new RaylibResultsInput($raylib),
        new RaylibResultsRenderer($raylib),
        $gateway,
        static function () use (&$request): void {
            $request = 'new';
        },
        static function () use (&$request): void {
            $request = 'menu';
        }
    );

    $menuContext = new MenuScreenContext(
        new RaylibMenuInput($raylib),
        new RaylibMenuScreenRenderer($raylib),
        CharacterChoiceCatalog::fromFile(
            dirname(__DIR__).'/config/characters.json'
        ),
        static function (string $id) use (
            &$request,
            &$chosenCharacter
        ): void {
            $chosenCharacter = ucfirst($id);
            $request = 'defeat';
        },
        static function () use (&$running): void {
            $running = false;
        },
    );

    $makeRun = static function (
        bool $victory,
        string $character
    ): FinalRunView {
        return new FinalRunView(
            bin2hex(random_bytes(16)),
            $character,
            $victory ? 12 : 5,
            $victory ? 80 : 25,
            $victory ? 640.0 : 210.0,
            $victory ? 2480 : 1250,
            $victory
        );
    };

    $states->push(
        new GameOverState(
            $resultsContext,
            $makeRun(false, $chosenCharacter)
        )
    );

    $states->flush();

    echo 'Banco de demonstracao: '.$database.PHP_EOL;
    echo 'F1: Game Over | F2: Vitoria | X: sair'.PHP_EOL;

    while ($running && ! $raylib->shouldClose()) {
        if ($raylib->isKeyPressed(290)) {
            $request = 'defeat';
        }

        if ($raylib->isKeyPressed(291)) {
            $request = 'victory';
        }

        $states->update(1.0 / 60.0);

        if ($request !== '') {
            $command = $request;
            $request = '';

            // Recria a pilha para nao deixar telas antigas abertas.
            while (! $states->isEmpty()) {
                $states->pop();
                $states->flush();
            }

            if ($command === 'menu' || $command === 'new') {
                $states->push(
                    new MenuState($menuContext, $resultsContext)
                );

                if ($command === 'new') {
                    $states->push(
                        new CharacterSelectState($menuContext)
                    );
                }
            } elseif ($command === 'victory') {
                $states->push(
                    new VictoryState(
                        $resultsContext,
                        $makeRun(true, $chosenCharacter)
                    )
                );
            } else {
                $states->push(
                    new GameOverState(
                        $resultsContext,
                        $makeRun(false, $chosenCharacter)
                    )
                );
            }

            $states->flush();
        }

        $raylib->beginDrawing();

        try {
            $states->render(0.0);
        } finally {
            $raylib->endDrawing();
        }
    }
} finally {
    $raylib->closeWindow();
}

echo 'D10: demonstracao encerrada.'.PHP_EOL;
