<?php

declare(strict_types=1);

use Jogo\Application\Graphical\ProgressionSnapshotProvider;
use Jogo\Application\Progression\ProgressionSession;
use Jogo\Core\EventBus;
use Jogo\Core\GameLoop;
use Jogo\Domain\Event\PlayerDied;
use Jogo\Domain\Progression\LevelUp\LevelUpOptionKind;
use Jogo\Domain\System\MovementSystem;
use Jogo\Domain\Value\Facing;
use Jogo\Domain\Value\InputState;
use Jogo\Domain\Value\MapBounds;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\WeaponDefinition;
use Jogo\Domain\Weapon\WeaponMutationGuard;
use Jogo\Infrastructure\Assets\AssetCatalog;
use Jogo\Infrastructure\Audio\AudioEventSubscriber;
use Jogo\Infrastructure\Audio\RaylibAudio;
use Jogo\Infrastructure\Feedback\FeedbackSubscribers;
use Jogo\Infrastructure\Feedback\VisualFeedbackPool;
use Jogo\Infrastructure\Feedback\VisualFeedbackSubscriber;
use Jogo\Infrastructure\Ffi\RaylibBinding;
use Jogo\Infrastructure\Input\RaylibInput;
use Jogo\Infrastructure\Menu\CharacterChoiceCatalog;
use Jogo\Infrastructure\Menu\MenuScreenContext;
use Jogo\Infrastructure\Menu\RaylibMenuInput;
use Jogo\Infrastructure\Menu\RaylibMenuScreenRenderer;
use Jogo\Infrastructure\Overlay\LevelUpScreenContext;
use Jogo\Infrastructure\Overlay\OverlayFramePresenter;
use Jogo\Infrastructure\Overlay\ReplacementSlotView;
use Jogo\Infrastructure\Overlay\UpgradeOptionView;
use Jogo\Infrastructure\Persistence\Migrator;
use Jogo\Infrastructure\Persistence\SqliteConnection;
use Jogo\Infrastructure\Persistence\SqliteScoreRepository;
use Jogo\Infrastructure\Play\PlayFrameFactory;
use Jogo\Infrastructure\Play\PlayFramePresenter;
use Jogo\Infrastructure\Rendering\Camera;
use Jogo\Infrastructure\Rendering\RaylibRenderer;
use Jogo\Infrastructure\Rendering\RaylibResourceCache;
use Jogo\Infrastructure\Results\BResultsGateway;
use Jogo\Infrastructure\Results\RaylibResultsInput;
use Jogo\Infrastructure\Results\RaylibResultsRenderer;
use Jogo\Infrastructure\Results\ResultsScreenContext;
use Jogo\Infrastructure\State\CharacterSelectState;
use Jogo\Infrastructure\State\GameOverState;
use Jogo\Infrastructure\State\LevelUpState;
use Jogo\Infrastructure\State\MenuState;
use Jogo\Infrastructure\State\PauseState;
use Jogo\Infrastructure\State\PlayState;
use Jogo\Infrastructure\State\StateLoopSynchronizer;
use Jogo\Infrastructure\State\StateStack;
use Jogo\Infrastructure\State\VictoryState;
use Jogo\Infrastructure\Time\MonotonicClock;

require __DIR__.'/vendor/autoload.php';

// D12: bootstrap do nucleo ja integrado A/B + interface D.
// Enquanto a Frente C nao for mesclada, o mapa e apenas visual.
// --demo habilita F8 (XP de teste) e F9 (fim artificial de teste).
$demo = in_array('--demo', $argv ?? [], true);
$startedAt = hrtime(true);
$root = __DIR__;
$catalog = AssetCatalog::fromProject($root);
$catalog->assertReady();

$pdo = SqliteConnection::open($root.'/database/jogo.sqlite');
(new Migrator($pdo, $root.'/database/migrations'))->migrate();
$gateway = new BResultsGateway(new SqliteScoreRepository($pdo));

$raylib = new RaylibBinding;
$raylib->openWindow(800, 450, 'Roguelike PHP - Integracao D12');
$raylib->setTargetFps(60);
$raylib->setExitKey(0);

$assets = RaylibResourceCache::fromRaylib($raylib);
$audio = RaylibAudio::fromRaylib(
    $raylib,
    $catalog->effects(),
    $catalog->tracks(),
);
$renderer = new RaylibRenderer(
    $raylib,
    new Camera(800, 450, new MapBounds(0, 0, 1600, 1000)),
    $assets,
    null,
    $catalog,
);
$menuInput = new RaylibMenuInput($raylib);
$gameInput = RaylibInput::fromRaylib($raylib);
$clock = new MonotonicClock;
$states = new StateStack;
$running = true;
$request = '';
$session = null;
$events = null;
$overlayPresenter = null;
$input = new InputState(Vector2::zero());

$results = new ResultsScreenContext(
    new RaylibResultsInput($raylib),
    new RaylibResultsRenderer($raylib, $catalog, $assets),
    $gateway,
    static function () use (&$request): void {
        $request = 'new';
    },
    static function () use (&$request): void {
        $request = 'menu';
    },
);

$menu = new MenuScreenContext(
    $menuInput,
    new RaylibMenuScreenRenderer($raylib, $catalog, $assets),
    CharacterChoiceCatalog::fromFile($root.'/config/characters.json'),
    static function (string $id) use (&$request): void {
        $request = 'start:'.$id;
    },
    static function () use (&$running): void {
        $running = false;
    },
);

$states->push(new MenuState($menu, $results));
$states->flush();

$loop = new GameLoop(
    $clock,
    static function (float $dt) use ($states): void {
        if ($states->top() instanceof PlayState) {
            $states->update($dt);
        }
    },
    static function (float $alpha) use ($states, $raylib): void {
        if ($states->top() instanceof PlayState
            || $states->top() instanceof PauseState
            || $states->top() instanceof LevelUpState) {
            $states->render($alpha);

            return;
        }
        $raylib->beginDrawing();
        try {
            $states->render($alpha);
        } finally {
            $raylib->endDrawing();
        }
    },
);
$sync = new StateLoopSynchronizer($states, $loop);

// Primeiro quadro real do menu inclui janela, assets, SQLite e composicao.
$raylib->beginDrawing();
try {
    $states->render(0.0);
} finally {
    $raylib->endDrawing();
}
$startupMs = (hrtime(true) - $startedAt) / 1_000_000;
echo sprintf("D12 - menu pronto em %.2f ms\n", $startupMs);
echo "WASD mover | P pausar | Q menu\n";
echo $demo
    ? "MODO DEMO: F8 concede XP real, F9 simula evento de morte.\n"
    : "Aviso: a logica da Frente C ainda nao esta integrada. Use --demo para o fluxo de teste.\n";

try {
    $audio->playMusic('menu');
    while ($running && ! $raylib->shouldClose()) {
        $top = $states->top();

        if ($top instanceof PauseState) {
            if ($raylib->isKeyPressed(80)) {
                $states->pop();
            } else {
                $top->handleInput($menuInput->poll(), $states);
            }
        } elseif ($top instanceof LevelUpState) {
            $top->handleInput($menuInput->poll(), $states);
        } elseif ($top instanceof PlayState) {
            $input = $gameInput->poll();
            if ($input->pauseRequested) {
                $states->push(new PauseState($overlayPresenter));
            }
            if ($raylib->isKeyPressed(81)) {
                $request = 'menu';
            }
        } else {
            // Menus e resultados recebem input mesmo se o GameLoop estiver pausado.
            $states->update(0.0);
        }
        $states->flush();

        if ($demo && $session !== null && $events !== null
            && $states->top() instanceof PlayState) {
            if ($raylib->isKeyPressed(297)) { // F8
                $xp = $session->experience->experience();
                $session->experience->grant(
                    $xp->requiredForNextLevel(),
                    $session->run->survivalSeconds(),
                );
            }
            if ($raylib->isKeyPressed(298)) { // F9
                // Evento artificial SOMENTE para inspecionar fim/ranking.
                $events->publish(new PlayerDied(
                    $session->player->id(),
                    $session->player->position(),
                    null,
                    $session->run->survivalSeconds(),
                ));
            }
        }

        if ($session !== null && $states->top() instanceof PlayState) {
            for ($attempt = 0; $attempt < 16
                && $session->levelUps->hasPendingChoice(); $attempt++) {
                $offer = $session->levelUps->currentOffer();
                if ($offer === []) {
                    continue;
                }
                $views = [];
                foreach ($offer as $index => $option) {
                    $type = match ($option->kind) {
                        LevelUpOptionKind::NEW_WEAPON => 'new_weapon',
                        LevelUpOptionKind::UPGRADE_WEAPON => 'weapon_upgrade',
                        LevelUpOptionKind::PASSIVE => 'passive',
                    };
                    $views[] = new UpgradeOptionView(
                        (string) $index,
                        $option->contentId,
                        $type,
                        'Melhoria real da Frente B',
                        'Nivel '.$option->level,
                        $option->requiresReplacement,
                    );
                }
                $slots = [];
                foreach ($session->player->inventory()->weapons() as $index => $weapon) {
                    $slots[] = new ReplacementSlotView(
                        (string) $index,
                        $weapon->definition()->id,
                    );
                }
                $ctx = new LevelUpScreenContext(
                    $views,
                    $slots,
                    static function (string $optionId, ?string $slotId) use ($session): bool {
                        $session->levelUps->choose(
                            (int) $optionId,
                            $slotId === null ? null : (int) $slotId
                        );

                        return true;
                    },
                );
                $states->push(new LevelUpState($ctx, $overlayPresenter));
                break;
            }
        }

        if ($request !== '') {
            $command = $request;
            $request = '';
            while (! $states->isEmpty()) {
                $states->pop();
                $states->flush();
            }

            if (str_starts_with($command, 'start:')) {
                $character = substr($command, 6);
                $events = new EventBus; // Uma instancia independente por partida.
                $guard = new class implements WeaponMutationGuard
                {
                    public function assertSafe(): void {}
                };
                $session = ProgressionSession::start(
                    $root.'/config',
                    $character,
                    'player-'.bin2hex(random_bytes(8)),
                    new Vector2(800.0, 500.0),
                    18.0,
                    160.0, // base provisoria de movimento, pendente de ratificacao
                    35.0,  // base provisoria de coleta, pendente de ratificacao
                    random_int(1, 1000000000),
                    $events,
                    static fn (WeaponDefinition $definition): bool => false,
                    $guard,
                );
                $provider = new ProgressionSnapshotProvider($session);
                $feedback = new VisualFeedbackPool($clock);
                (new FeedbackSubscribers(
                    new AudioEventSubscriber($audio),
                    new VisualFeedbackSubscriber(
                        $feedback,
                        static fn (): Vector2 => $session->player->position(),
                    ),
                ))->register($events);
                $events->subscribe(PlayerDied::class, static function (PlayerDied $event) use (&$request): void {
                    $request = 'end';
                });

                $factory = new PlayFrameFactory($feedback, $catalog);
                $presenter = new PlayFramePresenter($provider, $factory, $renderer);
                $overlayPresenter = new OverlayFramePresenter($provider, $factory, $renderer);
                $movement = new MovementSystem(new MapBounds(0, 0, 1600, 1000));
                $facing = new Facing(new Vector2(0.0, -1.0));
                $states->push(new PlayState(
                    static function (float $dt) use (
                        $session, $provider, $movement, $facing, &$input
                    ): void {
                        $provider->capturePrevious();
                        $movement->update(
                            $session->player,
                            $facing,
                            $input,
                            $session->player->movementSpeed(),
                            $dt,
                            ! $session->run->isEnded(),
                        );
                        // A etapa de combate da A/C sera conectada antes deste update.
                        $session->update($dt);
                    },
                    null,
                    $presenter,
                ));
                $audio->playMusic('game');
            } elseif ($command === 'end' && $session !== null) {
                // Fonte real da B: resumo + pontuacao. A C fornece a morte natural.
                $summary = $session->run->summary();
                $final = $gateway->register(
                    bin2hex(random_bytes(12)),
                    $summary,
                );
                $states->push($summary->won
                    ? new VictoryState($results, $final)
                    : new GameOverState($results, $final));
                $audio->playMusic('menu');
                $session = null;
                $events = null;
                $overlayPresenter = null;
            } else {
                $session = null;
                $events = null;
                $overlayPresenter = null;
                $states->push(new MenuState($menu, $results));
                if ($command === 'new') {
                    $states->push(new CharacterSelectState($menu));
                }
                $audio->playMusic('menu');
            }
            $states->flush();
        }

        $sync->synchronize();
        $loop->tick();
        $audio->update();
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

echo "D12 - encerrado normalmente.\n";
