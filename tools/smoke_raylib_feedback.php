<?php

declare(strict_types=1);

use Jogo\Core\EventBus;
use Jogo\Domain\Event\EnemyHit;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Event\ItemPicked;
use Jogo\Domain\Event\LevelUp;
use Jogo\Domain\Event\PlayerDamaged;
use Jogo\Domain\Event\PlayerDied;
use Jogo\Domain\Value\MapBounds;
use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Audio\AudioEventSubscriber;
use Jogo\Infrastructure\Audio\RaylibAudio;
use Jogo\Infrastructure\Feedback\ChestOpenedNotice;
use Jogo\Infrastructure\Feedback\FeedbackSubscribers;
use Jogo\Infrastructure\Feedback\FeedbackTypes;
use Jogo\Infrastructure\Feedback\VisualFeedbackPool;
use Jogo\Infrastructure\Feedback\VisualFeedbackSubscriber;
use Jogo\Infrastructure\Ffi\RaylibBinding;
use Jogo\Infrastructure\Play\PlayEntityView;
use Jogo\Infrastructure\Play\PlayFrameFactory;
use Jogo\Infrastructure\Play\PlayFramePresenter;
use Jogo\Infrastructure\Play\PlayHudSnapshot;
use Jogo\Infrastructure\Play\PlaySnapshot;
use Jogo\Infrastructure\Play\PlaySnapshotProviderInterface;
use Jogo\Infrastructure\Play\PlayWorldSnapshot;
use Jogo\Infrastructure\Rendering\Camera;
use Jogo\Infrastructure\Rendering\RaylibRenderer;
use Jogo\Infrastructure\Rendering\RaylibResourceCache;
use Jogo\Infrastructure\State\PlayState;
use Jogo\Infrastructure\State\StateStack;
use Jogo\Infrastructure\Time\MonotonicClock;

require dirname(__DIR__).'/vendor/autoload.php';

// Sete sons temporarios, sem assets de terceiros.
$sounds = [];
$sampleRate = 22050;

foreach (FeedbackTypes::ALL as $index => $kind) {
    $frequency = 300 + $index * 90;
    $pcm = '';

    for ($i = 0; $i < 3307; $i++) {
        $sample = (int) round(
            sin(2.0 * pi() * $frequency * $i / $sampleRate)
            * 5500
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
        .'/d9-feedback-'
        .bin2hex(random_bytes(8))
        .'.wav';

    if (file_put_contents($path, $wav) === false) {
        throw new RuntimeException(
            'Falha ao gerar som temporario da D9.'
        );
    }

    $sounds[$kind] = $path;
}

$raylib = null;
$audio = null;
$assets = null;

try {
    $raylib = new RaylibBinding;
    $raylib->openWindow(800, 450, 'D9 - Feedback Visual e Sonoro');
    $raylib->setTargetFps(60);

    $assets = RaylibResourceCache::fromRaylib($raylib);
    $audio = RaylibAudio::fromRaylib($raylib, $sounds);

    $clock = new MonotonicClock;
    $pool = new VisualFeedbackPool($clock, 96);
    $events = new EventBus;

    $player = new Vector2(780, 500);
    $enemy = new Vector2(910, 500);
    $chest = new Vector2(670, 590);

    $subscribers = new FeedbackSubscribers(
        new AudioEventSubscriber($audio),
        new VisualFeedbackSubscriber(
            $pool,
            static fn (): Vector2 => $player
        )
    );

    $subscribers->register($events);

    $provider = new class implements PlaySnapshotProviderInterface
    {
        public function snapshot(): PlaySnapshot
        {
            $player = new Vector2(780, 500);
            $enemy = new Vector2(910, 500);
            $chest = new Vector2(670, 590);

            return new PlaySnapshot(
                new PlayWorldSnapshot(
                    $player,
                    $player,
                    [
                        new PlayEntityView('enemy', $enemy, $enemy, 18),
                        new PlayEntityView('chest', $chest, $chest, 16),
                    ]
                ),
                new PlayHudSnapshot(
                    18,
                    20,
                    7,
                    13,
                    3,
                    30,
                    wave: 'D9 DEMO - PRESSIONE 1 A 7'
                )
            );
        }
    };

    $renderer = new RaylibRenderer(
        $raylib,
        new Camera(
            800,
            450,
            new MapBounds(0, 0, 1600, 1000)
        ),
        $assets
    );

    $presenter = new PlayFramePresenter(
        $provider,
        new PlayFrameFactory($pool),
        $renderer
    );

    $states = new StateStack;
    $states->push(new PlayState(presenter: $presenter));
    $states->flush();

    $start = microtime(true);

    while (
        ! $raylib->shouldClose()
        && microtime(true) - $start < 60.0
    ) {
        for ($i = 0; $i < 7; $i++) {
            if (! $raylib->isKeyPressed(49 + $i)) {
                continue;
            }

            $kind = FeedbackTypes::ALL[$i];

            echo 'D9: '.$kind.PHP_EOL;

            switch ($kind) {
                case 'player_damaged':
                    $events->publish(
                        new PlayerDamaged(
                            'player',
                            $player,
                            3,
                            17,
                            'enemy',
                            1
                        )
                    );
                    break;

                case 'enemy_hit':
                    $events->publish(
                        new EnemyHit(
                            'enemy-1',
                            'zombie',
                            $enemy,
                            5,
                            5,
                            'player',
                            'sword',
                            1
                        )
                    );
                    break;

                case 'enemy_killed':
                    $events->publish(
                        new EnemyKilled(
                            'enemy-1',
                            'zombie',
                            false,
                            $enemy,
                            'player',
                            'sword',
                            1
                        )
                    );
                    break;

                case 'level_up':
                    $events->publish(
                        new LevelUp('player', 2, 10, 1)
                    );
                    break;

                case 'chest_opened':
                    $events->publish(
                        new ChestOpenedNotice(
                            'chest-1',
                            $chest,
                            1
                        )
                    );
                    break;

                case 'player_died':
                    $events->publish(
                        new PlayerDied(
                            'player',
                            $player,
                            'enemy',
                            1
                        )
                    );
                    break;

                case 'item_picked':
                    $events->publish(
                        new ItemPicked(
                            'player',
                            'pickup-1',
                            'apple',
                            1
                        )
                    );
                    break;
            }
        }

        $audio->update();

        $states->render(0.0);
    }
} finally {
    try {
        if ($audio !== null) {
            $audio->close();
        }
    } finally {
        try {
            if ($assets !== null) {
                $assets->release();
            }

            if ($raylib !== null) {
                $raylib->closeWindow();
            }
        } finally {
            foreach ($sounds as $path) {
                @unlink($path);
            }
        }
    }
}

echo "D9: demonstracao encerrada.\n";
