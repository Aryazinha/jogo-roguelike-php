<?php

declare(strict_types=1);

use Jogo\Infrastructure\Ffi\RaylibBinding;

require dirname(__DIR__).'/vendor/autoload.php';

$raylib = new RaylibBinding;

$raylib->openWindow(800, 450, 'Meu primeiro Roguelike');
$raylib->setTargetFps(60);

$x = 400.0;
$start = microtime(true);

try {
    while (
        ! $raylib->shouldClose()
        && microtime(true) - $start < 30
    ) {
        if ($raylib->isKeyDown(263)) {
            $x -= 4.0;
        }

        if ($raylib->isKeyDown(262)) {
            $x += 4.0;
        }

        $x = max(30.0, min(770.0, $x));

        $raylib->beginDrawing();

        try {
            $raylib->clearBackground(35, 40, 55);

            $raylib->drawText(
                'MEU PRIMEIRO ROGUELIKE',
                150,
                65,
                30
            );

            $raylib->drawText(
                'Setas: mover | X: fechar',
                240,
                120,
                20,
                220,
                220,
                220
            );

            $raylib->drawRectangle(
                80,
                375,
                640,
                20,
                90,
                150,
                90
            );

            $raylib->drawCircle(
                $x,
                345.0,
                30.0,
                255,
                215,
                0
            );

            $raylib->drawText(
                'FPS: '.$raylib->getFps(),
                15,
                15,
                18
            );
        } finally {
            $raylib->endDrawing();
        }
    }
} finally {
    $raylib->closeWindow();
}

echo "Janela fechada com sucesso!\n";
