<?php

declare(strict_types=1);

use Jogo\Domain\Value\MapBounds;
use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Ffi\RaylibBinding;
use Jogo\Infrastructure\Rendering\Camera;
use Jogo\Infrastructure\Rendering\FrameSnapshot;
use Jogo\Infrastructure\Rendering\RaylibRenderer;
use Jogo\Infrastructure\Rendering\RaylibResourceCache;
use Jogo\Infrastructure\Rendering\RenderItem;

require dirname(__DIR__).'/vendor/autoload.php';

$raylib = new RaylibBinding;
$raylib->openWindow(800, 450, 'D3 - Renderer e camera');
$raylib->setTargetFps(60);

$camera = new Camera(
    800,
    450,
    new MapBounds(0, 0, 1600, 1000)
);

$assets = RaylibResourceCache::fromRaylib($raylib);

$fontPath = (getenv('WINDIR') ?: 'C:/Windows').'/Fonts/arial.ttf';

$renderer = new RaylibRenderer(
    $raylib,
    $camera,
    $assets,
    is_file($fontPath) ? $fontPath : null
);

$start = microtime(true);

try {
    while (
        ! $raylib->shouldClose()
        && microtime(true) - $start < 30
    ) {
        $seconds = microtime(true) - $start;

        // Cena de exemplo: somente dados visuais.
        $previous = new Vector2(
            700.0 + sin($seconds - 0.02) * 260.0,
            500.0 + cos($seconds - 0.02) * 180.0
        );

        $current = new Vector2(
            700.0 + sin($seconds) * 260.0,
            500.0 + cos($seconds) * 180.0
        );

        $items = [];

        for ($x = 100; $x <= 1500; $x += 100) {
            for ($y = 100; $y <= 900; $y += 100) {
                $position = new Vector2((float) $x, (float) $y);

                $items[] = new RenderItem(
                    'map',
                    'rectangle',
                    $position,
                    $position,
                    84.0,
                    40,
                    65,
                    52
                );
            }
        }

        $enemy = new Vector2(880.0, 540.0);
        $pickup = new Vector2(1030.0, 640.0);
        $effect = new Vector2(950.0, 350.0);

        $items[] = new RenderItem(
            'scenery',
            'rectangle',
            new Vector2(1100.0, 400.0),
            new Vector2(1100.0, 400.0),
            45.0,
            100,
            75,
            45
        );

        $items[] = new RenderItem(
            'pickups',
            'circle',
            $pickup,
            $pickup,
            18.0,
            250,
            220,
            40
        );

        $items[] = new RenderItem(
            'entities',
            'circle',
            $enemy,
            $enemy,
            38.0,
            210,
            65,
            70
        );

        $items[] = new RenderItem(
            'entities',
            'circle',
            $previous,
            $current,
            36.0,
            255,
            215,
            0
        );

        $items[] = new RenderItem(
            'attacks',
            'circle',
            new Vector2(800.0, 420.0),
            new Vector2(800.0, 420.0),
            12.0,
            255,
            255,
            255
        );

        $items[] = new RenderItem(
            'effects',
            'circle',
            $effect,
            $effect,
            12.0,
            100,
            190,
            255
        );

        $frame = new FrameSnapshot(
            $previous,
            $current,
            $items,
            [
                'D3 - RENDERER E CAMERA',
                'FPS: '.$raylib->getFps(),
                'Camera acompanha jogador',
                'Amarelo: jogador | Vermelho: inimigo',
            ]
        );

        $renderer->render($frame, 0.5);
    }
} finally {
    try {
        $assets->release();
    } finally {
        $raylib->closeWindow();
    }
}

echo "Teste grafico D3 encerrado.\n";
