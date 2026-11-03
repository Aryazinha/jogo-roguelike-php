<?php

declare(strict_types=1);

use Jogo\Core\GameLoop;
use Jogo\Domain\Value\MapBounds;
use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Ffi\RaylibBinding;
use Jogo\Infrastructure\Menu\RaylibMenuInput;
use Jogo\Infrastructure\Overlay\LevelUpScreenContext;
use Jogo\Infrastructure\Overlay\OverlayFramePresenter;
use Jogo\Infrastructure\Overlay\OverlayUiController;
use Jogo\Infrastructure\Overlay\ReplacementSlotView;
use Jogo\Infrastructure\Overlay\UpgradeOptionView;
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
use Jogo\Infrastructure\State\LevelUpState;
use Jogo\Infrastructure\State\PauseState;
use Jogo\Infrastructure\State\PlayState;
use Jogo\Infrastructure\State\StateLoopSynchronizer;
use Jogo\Infrastructure\State\StateStack;
use Jogo\Infrastructure\Time\MonotonicClock;

require dirname(__DIR__).'/vendor/autoload.php';

$provider = new class implements PlaySnapshotProviderInterface
{
    public float $seconds = 0.0;

    public function snapshot(): PlaySnapshot
    {
        $previous = new Vector2(
            800.0 + sin(($this->seconds - 0.016) * 0.7) * 140.0,
            500.0
        );

        $current = new Vector2(
            800.0 + sin($this->seconds * 0.7) * 140.0,
            500.0
        );

        $enemy = new Vector2(1000.0, 500.0);
        $pickup = new Vector2(650.0, 560.0);

        return new PlaySnapshot(
            new PlayWorldSnapshot(
                $previous,
                $current,
                [
                    new PlayEntityView(
                        'enemy',
                        $enemy,
                        $enemy,
                        20.0
                    ),
                    new PlayEntityView(
                        'pickup',
                        $pickup,
                        $pickup,
                        10.0
                    ),
                ]
            ),
            new PlayHudSnapshot(
                health: 18.0,
                maxHealth: 20.0,
                xp: 7,
                xpToNextLevel: 12,
                level: 3,
                elapsedSeconds: $this->seconds,
                weapons: [
                    new WeaponHudSnapshot('Stone Sword', 2, 0.0),
                    new WeaponHudSnapshot('Bow', 1, 0.0),
                ],
                passives: ['Sharpness'],
                wave: 'DEMO: P PAUSA | L LEVEL UP | N SEM OPCOES'
            )
        );
    }
};

$raylib = new RaylibBinding;
$raylib->openWindow(800, 450, 'D8 - Level Up e Pausa');
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
        $assets,
    );

    $factory = new PlayFrameFactory;

    $playPresenter = new PlayFramePresenter(
        $provider,
        $factory,
        $renderer
    );

    $overlayPresenter = new OverlayFramePresenter(
        $provider,
        $factory,
        $renderer
    );

    $states = new StateStack;

    $states->push(
        new PlayState(
            static function (float $dt) use ($provider): void {
                $provider->seconds += $dt;
            },
            presenter: $playPresenter
        )
    );

    $states->flush();

    $loop = new GameLoop(
        new MonotonicClock,
        static function (float $dt) use ($states): void {
            $states->update($dt);

        },
        static function (float $alpha) use ($states): void {
            $states->render($alpha);
        }
    );

    $sync = new StateLoopSynchronizer($states, $loop);

    $controller = new OverlayUiController(
        $states,
        new RaylibMenuInput($raylib),
        $sync,
    );

    $start = microtime(true);

    while (
        ! $raylib->shouldClose()
        && microtime(true) - $start < 90.0
    ) {
        $top = $states->top();

        if ($raylib->isKeyPressed(80)) {
            if ($top instanceof PauseState) {
                $states->pop();
            } elseif ($top instanceof PlayState) {
                $states->push(
                    new PauseState($overlayPresenter)
                );
            }
        }

        if (
            $top instanceof PlayState
            && $raylib->isKeyPressed(76)
        ) {
            $context = new LevelUpScreenContext(
                [
                    new UpgradeOptionView(
                        'upgrade_sword',
                        'Sharpness',
                        'weapon_upgrade',
                        'Melhoria da espada - DEMO',
                        'Aumentar dano (somente ilustrativo)'
                    ),
                    new UpgradeOptionView(
                        'new_bow',
                        'Nova arma',
                        'new_weapon',
                        'Escolher uma arma nova - DEMO',
                        'Exige selecionar um slot para substituir',
                        true
                    ),
                    new UpgradeOptionView(
                        'feather',
                        'Feather',
                        'passive',
                        'Item passivo - DEMO',
                        'Bonus ilustrativo de movimentacao'
                    ),
                ],
                [
                    new ReplacementSlotView('slot-1', 'Stone Sword'),
                    new ReplacementSlotView('slot-2', 'Bow'),
                ],
                static function (string $id, ?string $slot): bool {
                    echo sprintf(
                        "D8 DEMO: escolha=%s slot=%s\n",
                        $id,
                        $slot ?? 'nenhum'
                    );

                    // A demonstracao aceita, mas nao altera o dominio.
                    return true;
                }
            );

            $states->push(
                new LevelUpState($context, $overlayPresenter)
            );
        }

        if (
            $top instanceof PlayState
            && $raylib->isKeyPressed(78)
        ) {
            $states->push(
                new LevelUpState(
                    new LevelUpScreenContext(
                        [],
                        [],
                        static fn (string $id, ?string $slot): bool => false,
                        allowCancel: true,
                    ),
                    $overlayPresenter
                )
            );
        }

        // A entrada dos overlays continua funcionando durante a pausa.
        $controller->process();

        // Atualizacoes logicas ficam suspensas sob os overlays.
        $loop->tick();
    }
} finally {
    try {
        $assets->release();
    } finally {
        $raylib->closeWindow();
    }
}

echo "D8: demonstracao encerrada.\n";
