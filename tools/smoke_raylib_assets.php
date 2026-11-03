<?php

declare(strict_types=1);

use Jogo\Domain\Value\MapBounds;
use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Assets\AssetCatalog;
use Jogo\Infrastructure\Audio\RaylibAudio;
use Jogo\Infrastructure\Ffi\RaylibBinding;
use Jogo\Infrastructure\Menu\RaylibMenuScreenRenderer;
use Jogo\Infrastructure\Play\PlayEntityView;
use Jogo\Infrastructure\Play\PlayFrameFactory;
use Jogo\Infrastructure\Play\PlayHudSnapshot;
use Jogo\Infrastructure\Play\PlaySnapshot;
use Jogo\Infrastructure\Play\PlayWorldSnapshot;
use Jogo\Infrastructure\Rendering\Camera;
use Jogo\Infrastructure\Rendering\RaylibRenderer;
use Jogo\Infrastructure\Rendering\RaylibResourceCache;

require dirname(__DIR__).'/vendor/autoload.php';

$catalog = AssetCatalog::fromProject(dirname(__DIR__));
$catalog->assertReady();

$raylib = new RaylibBinding;
$raylib->openWindow(800, 450, 'D11 - Assets e Creditos');
$raylib->setTargetFps(60);
$raylib->setExitKey(0);

$assets = RaylibResourceCache::fromRaylib($raylib);

$audio = RaylibAudio::fromRaylib(
    $raylib,
    $catalog->effects(),
    $catalog->tracks()
);

$menu = new RaylibMenuScreenRenderer(
    $raylib,
    $catalog,
    $assets
);

$renderer = new RaylibRenderer(
    $raylib,
    new Camera(
        800,
        450,
        new MapBounds(0, 0, 1600, 1000)
    ),
    $assets,
    null,
    $catalog
);

$factory = new PlayFrameFactory(null, $catalog);

$player = new Vector2(780, 500);
$entities = [];

for ($x = 380; $x <= 1180; $x += 64) {
    for ($y = 260; $y <= 740; $y += 64) {
        $position = new Vector2((float) $x, (float) $y);

        $entities[] = new PlayEntityView(
            'map',
            $position,
            $position,
            32.0
        );
    }
}

$examples = [
    ['scenery', 490.0, 350.0, 30.0],
    ['scenery', 1080.0, 660.0, 30.0],
    ['enemy', 900.0, 500.0, 18.0],
    ['special_enemy', 950.0, 590.0, 20.0],
    ['boss', 1030.0, 400.0, 45.0],
    ['projectile', 835.0, 450.0, 10.0],
    ['sword', 830.0, 520.0, 20.0],
    ['tnt', 650.0, 620.0, 22.0],
    ['pulse', 680.0, 410.0, 15.0],
    ['chest', 590.0, 520.0, 22.0],
    ['pickup', 710.0, 620.0, 13.0],
    ['breakable', 550.0, 590.0, 25.0],
    ['effect', 980.0, 690.0, 15.0],
];

foreach ($examples as [$kind, $x, $y, $radius]) {
    $position = new Vector2($x, $y);

    $entities[] = new PlayEntityView(
        $kind,
        $position,
        $position,
        $radius
    );
}

$scene = new PlaySnapshot(
    new PlayWorldSnapshot(
        $player,
        $player,
        $entities
    ),
    new PlayHudSnapshot(
        24.0,
        30.0,
        8,
        20,
        3,
        60.0,
        wave: 'D11 - SPRITES ORIGINAIS'
    )
);

$mode = 'menu';

$audio->playMusic('menu');

echo "D11: M = menu, G = cena, 1 a 7 = sons, X = sair\n";

try {
    $start = $raylib->getTime();

    while (
        ! $raylib->shouldClose()
        && $raylib->getTime() - $start < 90.0
    ) {
        if ($raylib->isKeyPressed(88)) {
            break;
        }

        if ($raylib->isKeyPressed(77)) {
            $mode = 'menu';
            $audio->playMusic('menu');
        }

        if ($raylib->isKeyPressed(71)) {
            $mode = 'game';
            $audio->playMusic('game');
        }

        $effects = [
            'player_damaged',
            'enemy_hit',
            'enemy_killed',
            'level_up',
            'chest_opened',
            'player_died',
            'item_picked',
        ];

        foreach ($effects as $index => $effect) {
            if ($raylib->isKeyPressed(49 + $index)) {
                $audio->playEffect($effect);
                echo 'D11: som '.$effect.PHP_EOL;
            }
        }

        $audio->update();

        if ($mode === 'menu') {
            $raylib->beginDrawing();

            try {
                $menu->draw(
                    'D11 - RECURSOS',
                    [
                        'G - VER CENA',
                        'M - MENU',
                        'X - SAIR',
                    ],
                    0,
                    [
                        'Sprites e audio autorais.',
                        'Sons: teclas 1 a 7.',
                    ]
                );
            } finally {
                $raylib->endDrawing();
            }
        } else {
            $renderer->render(
                $factory->create($scene),
                0.0
            );
        }
    }
} finally {
    try {
        $audio->close();
    } finally {
        try {
            $assets->release();
        } finally {
            $raylib->closeWindow();
        }
    }
}

foreach ($audio->failures() as $failure) {
    fwrite(STDERR, $failure.PHP_EOL);
}

echo "D11: demonstracao encerrada.\n";
