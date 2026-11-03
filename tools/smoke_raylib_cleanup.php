<?php

declare(strict_types=1);

use Jogo\Infrastructure\Ffi\RaylibBinding;

require dirname(__DIR__).'/vendor/autoload.php';

// Monta os blocos de uma imagem PNG.
$chunk = static fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

// Cria uma imagem PNG temporaria.
$pixels = str_repeat(
    "\x00".str_repeat("\xff\xd7\x00\xff", 16),
    16
);

$compressed = gzcompress($pixels);

if ($compressed === false) {
    throw new RuntimeException(
        'Falha ao comprimir a imagem.'
    );
}

$png = "\x89PNG\r\n\x1a\n"
    .$chunk('IHDR', pack('N2C5', 16, 16, 8, 6, 0, 0, 0))
    .$chunk('IDAT', $compressed)
    .$chunk('IEND', '');

// Cria um WAV temporario e silencioso.
$pcm = str_repeat("\x00\x00", 2205);

$wav = 'RIFF'
    .pack('V', 36 + strlen($pcm))
    .'WAVE'
    .'fmt '
    .pack('V', 16)
    .pack('v2V2v2', 1, 1, 22050, 44100, 2, 16)
    .'data'
    .pack('V', strlen($pcm))
    .$pcm;

// Define arquivos temporarios.
$prefix = sys_get_temp_dir()
    .'/raylib-cleanup-'
    .bin2hex(random_bytes(6));

$pngPath = $prefix.'.png';
$wavPath = $prefix.'.wav';

$raylib = null;
$completed = 0;
$cycles = 600;

try {
    if (
        file_put_contents($pngPath, $png) === false
        || file_put_contents($wavPath, $wav) === false
    ) {
        throw new RuntimeException(
            'Falha ao criar arquivos temporarios.'
        );
    }

    // Inicializa a janela.
    $raylib = new RaylibBinding;

    $raylib->openWindow(
        800,
        450,
        'D1 - Teste de recursos'
    );

    $raylib->setTargetFps(60);

    // Inicializa o audio.
    $audioReady = $raylib->initAudio();

    // Procura uma fonte do Windows.
    $windowsDirectory = getenv('WINDIR')
        ?: 'C:/Windows';

    $candidateFont = $windowsDirectory.'/Fonts/arial.ttf';

    $fontPath = is_file($candidateFont)
        ? $candidateFont
        : null;

    // Repete os testes durante 600 quadros.
    for (
        $i = 0;
        $i < $cycles && ! $raylib->shouldClose();
        $i++
    ) {
        // Carrega uma textura em cada ciclo.
        $texture = $raylib->loadTexture($pngPath);

        $sound = null;
        $music = null;
        $font = null;

        try {
            // Testa sons a cada 10 ciclos.
            if ($audioReady && $i % 10 === 0) {
                $sound = $raylib->loadSound($wavPath);
            }

            // Testa musica a cada 60 ciclos.
            if ($audioReady && $i % 60 === 0) {
                $music = $raylib->loadMusic($wavPath);
            }

            // Testa fontes a cada 60 ciclos.
            if ($fontPath !== null && $i % 60 === 0) {
                $font = $raylib->loadFont($fontPath);
            }

            $raylib->beginDrawing();

            try {
                $raylib->clearBackground(35, 40, 55);

                $raylib->drawText(
                    'D1 - TESTE DE LIBERACAO',
                    25,
                    40,
                    26
                );

                $raylib->drawText(
                    'Ciclo '.($i + 1).' / '.$cycles,
                    25,
                    100,
                    22
                );

                $raylib->drawText(
                    'FPS: '.$raylib->getFps(),
                    25,
                    145,
                    20
                );

                $raylib->drawText(
                    'Audio: '.(
                        $audioReady
                            ? 'OK'
                            : 'indisponivel'
                    ),
                    25,
                    185,
                    20
                );

                $raylib->drawTexture(
                    $texture,
                    390,
                    215
                );

                if ($font !== null) {
                    $raylib->drawFontText(
                        $font,
                        'Fonte OK',
                        25,
                        240,
                        26.0
                    );
                }
            } finally {
                $raylib->endDrawing();
            }
        } finally {
            // Libera os recursos carregados.
            if ($font !== null) {
                $raylib->unloadFont($font);
            }

            if ($music !== null) {
                $raylib->unloadMusic($music);
            }

            if ($sound !== null) {
                $raylib->unloadSound($sound);
            }

            $raylib->unloadTexture($texture);
        }

        $completed++;

        if ($completed % 120 === 0) {
            echo "Ciclos concluidos: {$completed}\n";
        }
    }
} finally {
    try {
        if ($raylib !== null) {
            $raylib->closeWindow();
        }
    } finally {
        // Remove os arquivos temporarios.
        @unlink($pngPath);
        @unlink($wavPath);
    }
}

echo "Teste encerrado: {$completed} de {$cycles} ciclos.\n";
