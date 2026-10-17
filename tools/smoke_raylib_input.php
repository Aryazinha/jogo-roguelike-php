<?php

declare(strict_types=1);

use Jogo\Infrastructure\Ffi\RaylibBinding;
use Jogo\Infrastructure\Input\RaylibInput;

require dirname(__DIR__).'/vendor/autoload.php';

$raylib = new RaylibBinding;
$raylib->openWindow(800, 450, 'D2 - Controles');
$raylib->setTargetFps(60);

$input = RaylibInput::fromRaylib($raylib);
$pausas = 0;

try {
    while (! $raylib->shouldClose()) {
        $state = $input->poll();

        if ($state->pauseRequested) {
            $pausas++;
        }

        $raylib->beginDrawing();

        try {
            $raylib->clearBackground(35, 40, 55);
            $raylib->drawText('D2 - CONTROLES', 30, 35, 28);
            $raylib->drawText('WASD: mover | Setas: olhar | P: pausa', 30, 95, 20);

            $raylib->drawText(
                sprintf(
                    'Movimento: (%.0f, %.0f)',
                    $state->movement->x(),
                    $state->movement->y()
                ),
                30,
                150,
                20
            );

            $raylib->drawText(
                $state->facing === null
                    ? 'Olhar: manter direcao'
                    : sprintf(
                        'Olhar: (%.2f, %.2f)',
                        $state->facing->x(),
                        $state->facing->y()
                    ),
                30,
                195,
                20
            );

            $raylib->drawText(
                'Pausas solicitadas: '.$pausas,
                30,
                245,
                20
            );
        } finally {
            $raylib->endDrawing();
        }
    }
} finally {
    $raylib->closeWindow();
}

echo "Teste D2 encerrado.\n";
