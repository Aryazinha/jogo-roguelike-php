<?php

declare(strict_types=1);

use Jogo\Core\GameLoop;
use Jogo\Domain\Value\MapBounds;
use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Ffi\RaylibBinding;
use Jogo\Infrastructure\Play\BossHudSnapshot;
use Jogo\Infrastructure\Play\PlayEntityView;
use Jogo\Infrastructure\Play\PlayFrameFactory;
use Jogo\Infrastructure\Play\PlayFramePresenter;
use Jogo\Infrastructure\Play\PlayHudSnapshot;
use Jogo\Infrastructure\Play\PlaySnapshot;
use Jogo\Infrastructure\Play\PlaySnapshotProviderInterface;
use Jogo\Infrastructure\Play\PlayWorldSnapshot;
use Jogo\Infrastructure\Play\WeaponHudSnapshot;
use Jogo\Infrastructure\Rendering\Camera;
use Jogo\Infrastructure\Rendering\RaylibRenderer;
use Jogo\Infrastructure\Rendering\RaylibResourceCache;
use Jogo\Infrastructure\State\PlayState;
use Jogo\Infrastructure\State\StateStack;
use Jogo\Infrastructure\Time\MonotonicClock;

require dirname(__DIR__).'/vendor/autoload.php';

// Fonte de dados ficticia apenas para demonstracao grafica.
// Nao cria nem modifica entidades reais do jogo.
$provider = new class implements PlaySnapshotProviderInterface
{
    private float $start;

    public function __construct()
    {
        $this->start = microtime(true);
    }

    public function snapshot(): PlaySnapshot
    {
        $time = microtime(true) - $this->start;

        $previous = new Vector2(
            800.0 + sin(($time - 0.016) * 0.7) * 150.0,
            500.0 + cos(($time - 0.016) * 0.7) * 100.0
        );

        $current = new Vector2(
            800.0 + sin($time * 0.7) * 150.0,
            500.0 + cos($time * 0.7) * 100.0
        );

        $entities = [];

        for ($x = 150; $x <= 1450; $x += 80) {
            for ($y = 150; $y <= 850; $y += 80) {
                $position = new Vector2((float) $x, (float) $y);

                $entities[] = new PlayEntityView(
                    'map',
                    $position,
                    $position,
                    36.0
                );
            }
        }

        $objects = [
            ['enemy', 700.0, 420.0, 14.0],
            ['enemy', 920.0, 600.0, 14.0],
            ['special_enemy', 570.0, 590.0, 20.0],
            ['boss', 1050.0, 420.0, 39.0],
            ['chest', 910.0, 700.0, 17.0],
            ['pickup', 760.0, 640.0, 9.0],
            ['breakable', 560.0, 400.0, 22.0],
            ['scenery', 1150.0, 630.0, 23.0],
            ['projectile', 870.0, 490.0, 6.0],
            ['tnt', 1000.0, 570.0, 16.0],
            ['sword', 845.0, 555.0, 16.0],
            ['pulse', 680.0, 520.0, 19.0],
            ['effect', 950.0, 340.0, 8.0],
        ];

        foreach ($objects as [$kind, $x, $y, $radius]) {
            $position = new Vector2($x, $y);

            $entities[] = new PlayEntityView(
                $kind,
                $position,
                $position,
                $radius
            );
        }

        return new PlaySnapshot(
            new PlayWorldSnapshot(
                $previous,
                $current,
                $entities
            ),
            new PlayHudSnapshot(
                health: 16.0,
                maxHealth: 20.0,
                xp: 7,
                xpToNextLevel: 13,
                level: 3,
                elapsedSeconds: $time,
                weapons: [
                    new WeaponHudSnapshot('Stone Sword', 2, 0.4),
                    new WeaponHudSnapshot('Bow', 1, 0.8),
                ],
                passives: ['Sharpness', 'Feather'],
                boss: new BossHudSnapshot('Boss DEMO', 75.0, 100.0),
                wave: 'CENA DEMONSTRATIVA - D7'
            )
        );
    }
};

$raylib = new RaylibBinding;
$raylib->openWindow(800, 450, 'D7 - Partida e HUD');
$raylib->setTargetFps(60);

$assets = RaylibResourceCache::fromRaylib($raylib);

try {
    $renderer = new RaylibRenderer(
        $raylib,
        new Camera(
            800,
            450,
            new MapBounds(0.0, 0.0, 1600.0, 1000.0)
        ),
        $assets
    );

    $presenter = new PlayFramePresenter(
        $provider,
        new PlayFrameFactory,
        $renderer
    );

    $states = new StateStack;

    $states->push(
        new PlayState(presenter: $presenter)
    );

    $states->flush();

    $loop = new GameLoop(
        new MonotonicClock,
        static function (float $deltaSeconds) use ($states): void {
            $states->update($deltaSeconds);
        },
        static function (float $alpha) use ($states): void {
            $states->render($alpha);
        }
    );

    $start = microtime(true);

    while (
        ! $raylib->shouldClose()
        && microtime(true) - $start < 30.0
    ) {
        $loop->tick();
    }
} finally {
    try {
        $assets->release();
    } finally {
        $raylib->closeWindow();
    }
}

echo "D7: demonstracao grafica encerrada.\n";
