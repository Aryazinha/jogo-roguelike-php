<?php

declare(strict_types=1);

use Jogo\Infrastructure\Audio\RaylibAudio;
use Jogo\Infrastructure\Ffi\RaylibBinding;

require dirname(__DIR__).'/vendor/autoload.php';

// Gera um WAV temporario para testar sem baixar assets.
$sampleRate = 22050;
$pcm = '';

for ($i = 0; $i < $sampleRate; $i++) {
    $sample = (int) round(
        sin(2.0 * pi() * 440.0 * $i / $sampleRate) * 3500
    );

    $pcm .= pack('v', $sample & 0xFFFF);
}

$wav = 'RIFF'
    .pack('V', 36 + strlen($pcm))
    .'WAVE'
    .'fmt '
    .pack('V', 16)
    .pack('v2V2v2', 1, 1, $sampleRate, $sampleRate * 2, 2, 16)
    .'data'
    .pack('V', strlen($pcm))
    .$pcm;

$path = sys_get_temp_dir()
    .'/raylib-d4-'
    .bin2hex(random_bytes(6))
    .'.wav';

if (file_put_contents($path, $wav) === false) {
    throw new RuntimeException('Nao foi possivel criar o WAV temporario.');
}

$raylib = null;
$audio = null;

try {
    $raylib = new RaylibBinding;

    $raylib->openWindow(
        800,
        450,
        'D4 - Audio'
    );

    $raylib->setTargetFps(60);

    $audio = RaylibAudio::fromRaylib(
        $raylib,
        [
            'enemy_hit' => $path,
            'level_up' => $path,
        ],
        [
            'menu' => $path,
            'game' => $path,
        ]
    );

    $audio->playMusic('menu');

    $start = $raylib->getTime();
    $lastAction = 'Musica do menu';

    while (
        ! $raylib->shouldClose()
        && $raylib->getTime() - $start < 30.0
    ) {
        if ($raylib->isKeyPressed(49)) {
            $audio->playEffect('enemy_hit');
            $lastAction = 'Efeito: inimigo atingido';
        }

        if ($raylib->isKeyPressed(50)) {
            $audio->playEffect('level_up');
            $lastAction = 'Efeito: level up';
        }

        if ($raylib->isKeyPressed(77)) {
            $audio->playMusic('menu');
            $lastAction = 'Musica: menu';
        }

        if ($raylib->isKeyPressed(71)) {
            $audio->playMusic('game');
            $lastAction = 'Musica: partida';
        }

        if ($raylib->isKeyPressed(83)) {
            $audio->stopMusic();
            $lastAction = 'Musica parada';
        }

        // Musicas em streaming precisam ser atualizadas por quadro.
        $audio->update();

        $raylib->beginDrawing();

        try {
            $raylib->clearBackground(30, 35, 50);

            $raylib->drawText('D4 - AUDIO', 30, 35, 30);

            $raylib->drawText(
                '1: efeito de dano',
                30,
                100,
                20
            );

            $raylib->drawText(
                '2: efeito de level up',
                30,
                140,
                20
            );

            $raylib->drawText(
                'M: musica do menu',
                30,
                180,
                20
            );

            $raylib->drawText(
                'G: musica da partida',
                30,
                220,
                20
            );

            $raylib->drawText(
                'S: parar musica',
                30,
                260,
                20
            );

            $raylib->drawText(
                'Audio: '.(
                    $audio->isAvailable()
                        ? 'disponivel'
                        : 'indisponivel'
                ),
                30,
                310,
                20
            );

            $raylib->drawText(
                'Ultima acao: '.$lastAction,
                30,
                350,
                18
            );
        } finally {
            $raylib->endDrawing();
        }
    }
} finally {
    try {
        if ($audio !== null) {
            $audio->close();

            foreach ($audio->failures() as $failure) {
                fwrite(STDERR, $failure.PHP_EOL);
            }
        }
    } finally {
        try {
            if ($raylib !== null) {
                $raylib->closeWindow();
            }
        } finally {
            @unlink($path);
        }
    }
}

echo "Teste grafico D4 encerrado.\n";
