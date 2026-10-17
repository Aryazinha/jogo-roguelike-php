<?php

declare(strict_types=1);

use Jogo\Infrastructure\Ffi\RaylibBinding;
use Jogo\Infrastructure\Menu\CharacterChoiceCatalog;
use Jogo\Infrastructure\Menu\MenuScreenContext;
use Jogo\Infrastructure\Menu\RaylibMenuInput;
use Jogo\Infrastructure\Menu\RaylibMenuScreenRenderer;
use Jogo\Infrastructure\State\MenuState;
use Jogo\Infrastructure\State\StateStack;

require dirname(__DIR__).'/vendor/autoload.php';

$characters = CharacterChoiceCatalog::fromFile(
    dirname(__DIR__).'/config/characters.json'
);

$raylib = new RaylibBinding;
$raylib->openWindow(800, 450, 'D6 - Menu e selecao');
$raylib->setTargetFps(60);

$states = new StateStack;
$running = true;
$selectedId = null;

$ui = null;

try {
    $ui = new MenuScreenContext(
        new RaylibMenuInput($raylib),
        new RaylibMenuScreenRenderer($raylib),
        $characters,
        static function (string $id) use (
            &$selectedId,
            &$ui,
            $states
        ): void {
            $selectedId = $id;

            echo 'Personagem escolhido: '.$id.PHP_EOL;

            if ($ui !== null) {
                // Retorna ao menu nesta demonstracao.
                // A Application iniciara a partida real futuramente.
                $states->pop();
            }
        },
        static function () use (&$running): void {
            $running = false;
        },
    );

    $states->push(new MenuState($ui));
    $states->flush();

    while ($running && ! $raylib->shouldClose()) {
        // Entrada de menu uma vez por quadro.
        $states->update(1.0 / 60.0);

        $raylib->beginDrawing();

        try {
            $states->render(0.0);

            if ($selectedId !== null) {
                $raylib->drawText(
                    'Ultima escolha: '.$selectedId,
                    65,
                    426,
                    16,
                    255,
                    215,
                    100
                );
            }
        } finally {
            $raylib->endDrawing();
        }
    }
} finally {
    $raylib->closeWindow();
}

echo "Teste grafico D6 encerrado.\n";
