<?php

declare(strict_types=1);

/**
 * Gera recursos originais sem baixar arquivos de terceiros.
 * Executar: php tools/generate_d11_assets.php
 */
final class D11PixelCanvas
{
    /** @var array<int, string> */
    private array $pixels;

    public function __construct()
    {
        $this->pixels = array_fill(
            0,
            32 * 32,
            "\x00\x00\x00\x00"
        );
    }

    public function rect(
        int $x,
        int $y,
        int $width,
        int $height,
        string $color,
    ): void {
        $rgba = self::color($color);

        for ($py = max(0, $y); $py < min(32, $y + $height); $py++) {
            for ($px = max(0, $x); $px < min(32, $x + $width); $px++) {
                $this->pixels[$py * 32 + $px] = $rgba;
            }
        }
    }

    public function circle(
        int $cx,
        int $cy,
        int $radius,
        string $color,
    ): void {
        $rgba = self::color($color);

        for ($y = max(0, $cy - $radius); $y < min(32, $cy + $radius + 1); $y++) {
            for ($x = max(0, $cx - $radius); $x < min(32, $cx + $radius + 1); $x++) {
                if (($x - $cx) ** 2 + ($y - $cy) ** 2 <= $radius ** 2) {
                    $this->pixels[$y * 32 + $x] = $rgba;
                }
            }
        }
    }

    public function png(): string
    {
        $raw = '';

        for ($y = 0; $y < 32; $y++) {
            $raw .= "\x00";

            for ($x = 0; $x < 32; $x++) {
                $raw .= $this->pixels[$y * 32 + $x];
            }
        }

        return "\x89PNG\r\n\x1a\n"
            .self::chunk('IHDR', pack('NNCCCCC', 32, 32, 8, 6, 0, 0, 0))
            .self::chunk('IDAT', $this->compress($raw))
            .self::chunk('IEND', '');
    }

    private function compress(string $raw): string
    {
        $compressed = gzcompress($raw, 9);

        if ($compressed === false) {
            throw new RuntimeException(
                'Falha ao comprimir o sprite PNG.'
            );
        }

        return $compressed;
    }

    private static function color(string $hex): string
    {
        $hex = ltrim($hex, '#');

        return pack(
            'C4',
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
            255
        );
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data))
            .$type
            .$data
            .hash('crc32b', $type.$data, true);
    }
}

$root = dirname(__DIR__);
$assetRoot = $root.'/assets';

@mkdir($assetRoot.'/sprites', 0777, true);
@mkdir($assetRoot.'/audio', 0777, true);

/**
 * Cada forma:
 * r = retangulo (x, y, largura, altura, cor)
 * c = circulo (centroX, centroY, raio, cor)
 *
 * Todas as imagens sao desenhos originais de 32x32 pixels.
 */
$blueprints = [
    'map' => [
        ['r', 0, 0, 32, 32, '355940'],
        ['r', 1, 1, 30, 30, '42754A'],
        ['r', 4, 6, 7, 3, '50945A'],
        ['r', 19, 22, 8, 3, '31583A'],
        ['r', 21, 7, 4, 4, '559C5E'],
    ],
    'scenery' => [
        ['r', 13, 17, 7, 14, '513B2B'],
        ['r', 15, 19, 3, 9, '836142'],
        ['r', 6, 8, 22, 16, '142E28'],
        ['r', 8, 4, 18, 18, '286C42'],
        ['r', 11, 1, 12, 20, '39985A'],
        ['r', 9, 8, 6, 5, '57AD65'],
    ],
    'player' => [
        ['r', 9, 3, 15, 3, '222A34'],
        ['r', 7, 6, 19, 13, '25303B'],
        ['r', 9, 8, 15, 11, 'F1BA80'],
        ['r', 11, 12, 3, 3, '243449'],
        ['r', 20, 12, 3, 3, '243449'],
        ['r', 11, 19, 13, 9, '239C9B'],
        ['r', 8, 20, 3, 8, 'E7AC70'],
        ['r', 24, 20, 3, 8, 'E7AC70'],
        ['r', 12, 28, 5, 4, '293B70'],
        ['r', 19, 28, 5, 4, '293B70'],
        ['r', 16, 21, 4, 4, '78D5BD'],
    ],
    'enemy' => [
        ['r', 7, 5, 19, 17, '173F32'],
        ['r', 9, 7, 15, 13, '70A95B'],
        ['r', 11, 12, 4, 3, 'B83B4A'],
        ['r', 19, 12, 4, 3, 'B83B4A'],
        ['r', 10, 20, 15, 9, '356D82'],
        ['r', 12, 28, 5, 4, '283D5E'],
        ['r', 20, 28, 5, 4, '283D5E'],
    ],
    'special_enemy' => [
        ['r', 5, 3, 6, 9, '5B317D'],
        ['r', 23, 3, 6, 9, '5B317D'],
        ['r', 7, 9, 21, 15, '2C224C'],
        ['r', 10, 10, 15, 12, '9254B0'],
        ['r', 12, 14, 4, 3, 'FFDA7F'],
        ['r', 20, 14, 4, 3, 'FFDA7F'],
        ['r', 11, 24, 15, 8, '4F397A'],
    ],
    'boss' => [
        ['r', 3, 4, 6, 12, '6F1C3C'],
        ['r', 24, 4, 6, 12, '6F1C3C'],
        ['r', 5, 13, 24, 18, '451E34'],
        ['r', 7, 9, 20, 16, 'B7374B'],
        ['r', 11, 5, 12, 5, 'F2B544'],
        ['r', 12, 13, 5, 4, 'F5E8D2'],
        ['r', 21, 13, 5, 4, 'F5E8D2'],
        ['r', 14, 22, 8, 4, 'F2B544'],
    ],
    'projectile' => [
        ['c', 16, 16, 10, '6B8BD5'],
        ['c', 16, 16, 7, 'A8E1F4'],
        ['c', 14, 13, 3, 'FFFFFF'],
    ],
    'sword' => [
        ['r', 18, 2, 5, 19, 'C9DBE8'],
        ['r', 19, 3, 2, 18, 'FFFFFF'],
        ['r', 10, 20, 20, 4, '4C5E70'],
        ['r', 15, 24, 5, 7, '785035'],
        ['r', 14, 29, 7, 3, 'F2BE62'],
    ],
    'tnt' => [
        ['r', 5, 6, 23, 23, '3A2929'],
        ['r', 7, 7, 19, 20, 'D95349'],
        ['r', 7, 14, 19, 8, 'F6EBD0'],
        ['r', 11, 16, 3, 4, '4B3230'],
        ['r', 16, 16, 3, 4, '4B3230'],
        ['r', 21, 16, 3, 4, '4B3230'],
        ['r', 13, 2, 3, 6, 'F2D571'],
    ],
    'pulse' => [
        ['c', 16, 16, 15, '215B86'],
        ['c', 16, 16, 11, '46C5D7'],
        ['c', 16, 16, 6, '8BEAF2'],
        ['c', 16, 16, 3, 'FFFFFF'],
    ],
    'chest' => [
        ['r', 3, 8, 26, 22, '33291D'],
        ['r', 5, 10, 22, 7, 'B17A3B'],
        ['r', 5, 18, 22, 10, '7F4E2F'],
        ['r', 6, 18, 20, 3, 'E8B55F'],
        ['r', 14, 15, 6, 10, 'E8B55F'],
        ['r', 16, 17, 2, 4, '5A3A25'],
    ],
    'pickup' => [
        ['c', 16, 16, 12, '7C5528'],
        ['c', 16, 16, 9, 'EDBD56'],
        ['r', 14, 9, 4, 15, 'FFF3B2'],
        ['r', 10, 14, 12, 4, 'FFF3B2'],
    ],
    'breakable' => [
        ['r', 3, 5, 26, 24, '45382E'],
        ['r', 6, 8, 20, 18, 'B9A17B'],
        ['r', 14, 8, 3, 11, '574939'],
        ['r', 11, 18, 11, 3, '574939'],
        ['r', 19, 21, 3, 5, '574939'],
    ],
    'effect' => [
        ['c', 16, 16, 12, '3384C6'],
        ['c', 16, 16, 7, '79E8F6'],
        ['c', 16, 16, 3, 'FFFFFF'],
    ],
    'hud_heart' => [
        ['c', 11, 11, 7, 'D83C58'],
        ['c', 21, 11, 7, 'D83C58'],
        ['r', 7, 11, 18, 8, 'D83C58'],
        ['r', 10, 17, 13, 5, 'D83C58'],
        ['r', 13, 22, 7, 5, 'D83C58'],
        ['r', 15, 27, 3, 3, 'D83C58'],
        ['r', 9, 8, 4, 3, 'FFABB6'],
    ],
    'hud_xp' => [
        ['c', 16, 17, 11, '205C97'],
        ['c', 16, 17, 8, '59B6E6'],
        ['r', 14, 8, 4, 17, 'D3F4FF'],
        ['r', 8, 15, 16, 4, 'D3F4FF'],
    ],
    'menu_emblem' => [
        ['r', 3, 3, 26, 26, '213C39'],
        ['r', 5, 5, 22, 22, '4C996C'],
        ['r', 9, 6, 14, 5, 'F8CD60'],
        ['r', 11, 11, 10, 15, 'F6D99B'],
        ['r', 13, 16, 6, 6, '456A59'],
        ['r', 15, 10, 2, 9, 'FFFFFF'],
    ],
    'menu_panel' => [
        ['r', 1, 1, 30, 30, '293F43'],
        ['r', 3, 3, 26, 26, '40785B'],
        ['r', 5, 5, 22, 22, '2F5B49'],
        ['r', 6, 6, 20, 3, '659D78'],
    ],
    'victory' => [
        ['r', 5, 5, 5, 14, 'EBC35A'],
        ['r', 22, 5, 5, 14, 'EBC35A'],
        ['r', 10, 4, 12, 21, 'EBC35A'],
        ['r', 12, 25, 8, 4, '98713A'],
        ['r', 8, 29, 16, 3, 'EBC35A'],
        ['r', 13, 10, 6, 8, 'F9E8AA'],
    ],
    'defeat' => [
        ['c', 16, 14, 12, 'D5D1C4'],
        ['r', 8, 19, 16, 11, 'D5D1C4'],
        ['r', 9, 13, 5, 5, '35404A'],
        ['r', 19, 13, 5, 5, '35404A'],
        ['r', 15, 19, 3, 4, '35404A'],
        ['r', 11, 27, 2, 5, '35404A'],
        ['r', 16, 27, 2, 5, '35404A'],
        ['r', 21, 27, 2, 5, '35404A'],
    ],
];

$manifest = [
    'version' => 1,
    'font' => 'raylib_builtin',
    'sprites' => [],
    'effects' => [],
    'tracks' => [],
];

$creditsRows = [];

foreach ($blueprints as $name => $shapes) {
    $canvas = new D11PixelCanvas;

    foreach ($shapes as $shape) {
        if ($shape[0] === 'r') {
            $canvas->rect(
                (int) $shape[1],
                (int) $shape[2],
                (int) $shape[3],
                (int) $shape[4],
                (string) $shape[5]
            );
        } else {
            $canvas->circle(
                (int) $shape[1],
                (int) $shape[2],
                (int) $shape[3],
                (string) $shape[4]
            );
        }
    }

    $relative = 'assets/sprites/'.$name.'.png';

    file_put_contents($root.'/'.$relative, $canvas->png());

    $manifest['sprites'][$name] = $relative;
    $creditsRows[] = $relative;
}

$sampleRate = 11025;

$tones = [
    'player_damaged' => [175.0, 0.21],
    'enemy_hit' => [410.0, 0.17],
    'enemy_killed' => [270.0, 0.38],
    'level_up' => [700.0, 0.65],
    'chest_opened' => [530.0, 0.45],
    'player_died' => [130.0, 0.92],
    'item_picked' => [860.0, 0.24],
    'menu' => [220.0, 4.0],
    'game' => [147.0, 4.0],
];

foreach ($tones as $name => [$frequency, $seconds]) {
    $music = $name === 'menu' || $name === 'game';
    $count = (int) round($seconds * $sampleRate);
    $pcm = '';

    for ($i = 0; $i < $count; $i++) {
        $time = $i / $sampleRate;
        $progress = $i / $count;

        $envelope = $music
            ? 0.65
            : (1.0 - $progress) ** 2;

        $note = $music
            ? ((int) floor($time * 2.0) % 4)
            : 0;

        $tone = $frequency * (
            $music
                ? [1.0, 1.25, 1.5, 1.25][$note]
                : 1.0
        );

        $wave = sin(2.0 * pi() * $tone * $time)
            + 0.22 * sin(4.0 * pi() * $tone * $time);

        $sample = (int) round(6500 * $envelope * $wave);

        $pcm .= pack('v', $sample & 0xFFFF);
    }

    $wav = 'RIFF'
        .pack('V', 36 + strlen($pcm))
        .'WAVEfmt '
        .pack(
            'VvvVVvv',
            16,
            1,
            1,
            $sampleRate,
            $sampleRate * 2,
            2,
            16
        )
        .'data'
        .pack('V', strlen($pcm))
        .$pcm;

    $relative = 'assets/audio/'.$name.'.wav';

    file_put_contents($root.'/'.$relative, $wav);

    $manifest[$music ? 'tracks' : 'effects'][$name] = $relative;
    $creditsRows[] = $relative;
}

file_put_contents(
    $assetRoot.'/manifest.json',
    json_encode(
        $manifest,
        JSON_PRETTY_PRINT
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR
    )."\n"
);

$credits = <<<'MD'
# Créditos e licenças — D11

Os recursos abaixo são originais, gerados programaticamente
pelo arquivo `tools/generate_d11_assets.php`.

Nenhum arquivo original do Minecraft foi utilizado.
Nenhum sprite, som ou fonte externa foi baixado.

- **Origem:** geração procedural local em PHP
- **Autor/responsável:** Frente D, com assistência do ChatGPT
- **Licença/uso:** recursos autorais autorizados para uso e distribuição neste projeto
- **Data:** 2026-10-17

| Arquivo | Origem | Autor | Licença/uso | Responsável | Data |
| --- | --- | --- | --- | --- | --- |
MD;

foreach ($creditsRows as $relative) {
    $credits .= "\n| `".$relative.'`'
        .' | Gerador PHP do projeto'
        .' | Frente D + ChatGPT'
        .' | Original, uso autorizado no projeto'
        .' | Frente D'
        .' | 2026-10-17 |';
}

$credits .= <<<'MD'


## Fonte

A interface usa a fonte padrão embutida na Raylib 5.5.
Nenhum TTF externo é distribuído.

A biblioteca Raylib segue sua licença zlib e é instalada
separadamente, conforme `docs/AMBIENTE.md`.

## Música

`menu.wav` e `game.wav` são duas faixas simples,
originais, geradas para esta entrega.

A Raylib suporta reprodução de WAV como música.
Elas podem ser substituídas por OGG licenciado no futuro.

## Atribuição

Nenhum dos recursos originais desta entrega exige
uma tela adicional de atribuição.

Caso sejam incluídos recursos de terceiros, conferir
a licença e adicionar os créditos exigidos antes do commit.
MD;

file_put_contents(
    $assetRoot.'/CREDITOS.md',
    $credits."\n"
);

echo 'D11: '
    .count($blueprints)
    .' sprites, '
    .count($tones)
    .' arquivos WAV e creditos gerados.'
    .PHP_EOL;
