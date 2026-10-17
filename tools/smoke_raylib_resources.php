<?php

declare(strict_types=1);

use Jogo\Infrastructure\Ffi\RaylibBinding;

require dirname(__DIR__).'/vendor/autoload.php';

// Funcao para montar os blocos de uma imagem PNG.
$chunk = static fn (string $kind, string $bytes): string => pack('N', strlen($bytes)).$kind.$bytes.pack('N', crc32($kind.$bytes));

// Criar os pixels da imagem temporaria.
$pixels = str_repeat(
    "\x00".str_repeat("\xff\xd7\x00\xff", 16),
    16
);

// Comprimir os pixels.
$compressed = gzcompress($pixels);

if ($compressed === false) {
    throw new RuntimeException(
        'Nao foi possivel comprimir a imagem PNG.'
    );
}

// Montar a imagem PNG.
$png = "\x89PNG\r\n\x1a\n"
    .$chunk('IHDR', pack('N2C5', 16, 16, 8, 6, 0, 0, 0))
    .$chunk('IDAT', $compressed)
    .$chunk('IEND', '');

// Criar um audio WAV silencioso para teste.
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

// Caminhos dos arquivos temporarios.
$prefix = sys_get_temp_dir()
    .'/raylib-d1-'
    .bin2hex(random_bytes(6));

$pngFile = $prefix.'.png';
$wavFile = $prefix.'.wav';

$raylib = null;

try {
    if (
        file_put_contents($pngFile, $png) === false
        || file_put_contents($wavFile, $wav) === false
    ) {
        throw new RuntimeException(
            'Nao foi possivel criar os recursos temporarios.'
        );
    }

    // Iniciar a biblioteca grafica.
    $raylib = new RaylibBinding;

    $raylib->openWindow(
        800,
        450,
        'Raylib D1 - Recursos'
    );

    $raylib->setTargetFps(60);

    // Carregar a textura.
    $texture = $raylib->loadTexture($pngFile);

    // Inicializar audio.
    $audioOk = $raylib->initAudio();

    // Carregar efeito sonoro.
    $sound = $audioOk
        ? $raylib->loadSound($wavFile)
        : null;

    // Carregar musica.
    $music = $audioOk
        ? $raylib->loadMusic($wavFile)
        : null;

    if ($music !== null) {
        $raylib->playMusic($music);
    }

    // Procurar uma fonte instalada no Windows.
    $fontPath = (getenv('WINDIR') ?: 'C:/Windows')
        .'/Fonts/arial.ttf';

    $font = is_file($fontPath)
        ? $raylib->loadFont($fontPath)
        : null;

    // Iniciar o tempo de teste.
    $start = $raylib->getTime();

    while (
        ! $raylib->shouldClose()
        && $raylib->getTime() - $start < 20
    ) {
        // Atualizar a musica.
        if ($music !== null) {
            $raylib->updateMusic($music);
        }

        // Reproduzir som ao pressionar espaco.
        if (
            $sound !== null
            && $raylib->isKeyPressed(32)
        ) {
            $raylib->playSound($sound);
        }

        // Iniciar desenho.
        $raylib->beginDrawing();

        try {
            // Cor do fundo.
            $raylib->clearBackground(
                35,
                40,
                55
            );

            // Titulo.
            $raylib->drawText(
                'D1: TEXTURA, MOUSE E AUDIO',
                30,
                35,
                25
            );

            // Desenhar textura na posicao do mouse.
            $raylib->drawTexture(
                $texture,
                $raylib->getMouseX(),
                $raylib->getMouseY()
            );

            // Instrucoes.
            $raylib->drawText(
                'Mova o mouse | ESPACO: som | X: sair',
                30,
                110,
                18
            );

            // Mostrar FPS.
            $raylib->drawText(
                'FPS: '.$raylib->getFps(),
                30,
                150,
                20
            );

            // Estado do audio.
            $raylib->drawText(
                'Audio: '.(
                    $audioOk
                        ? 'disponivel'
                        : 'indisponivel'
                ),
                30,
                190,
                20
            );

            // Teste de fonte personalizada.
            if ($font !== null) {
                $raylib->drawFontText(
                    $font,
                    'FONTE CARREGADA',
                    30,
                    245,
                    28.0
                );
            }
        } finally {
            $raylib->endDrawing();
        }
    }
} finally {
    // Encerrar janela e liberar recursos.
    try {
        if ($raylib !== null) {
            $raylib->closeWindow();
        }
    } finally {
        // Excluir arquivos temporarios.
        @unlink($pngFile);
        @unlink($wavFile);
    }
}

echo "Teste D1 encerrado.\n";
