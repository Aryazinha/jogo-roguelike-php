<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Infrastructure\Audio;

use Jogo\Core\EventBus;
use Jogo\Domain\Event\ItemPicked;
use Jogo\Domain\Event\LevelUp;
use Jogo\Domain\Event\PlayerDied;
use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Audio\AudioDeviceInterface;
use Jogo\Infrastructure\Audio\AudioEventSubscriber;
use Jogo\Infrastructure\Audio\AudioInterface;
use Jogo\Infrastructure\Audio\NullAudio;
use Jogo\Infrastructure\Audio\RaylibAudio;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AudioTest extends TestCase
{
    public function test_null_audio_accepts_operations(): void
    {
        $audio = new NullAudio;

        self::assertInstanceOf(AudioInterface::class, $audio);

        $audio->playEffect('enemy_hit');
        $audio->playMusic('menu');
        $audio->update();
        $audio->stopMusic();
        $audio->close();

    }

    public function test_unavailable_device_does_not_interrupt_game(): void
    {
        $device = new FakeAudioDevice(false);

        $audio = new RaylibAudio(
            $device,
            ['enemy_hit' => __FILE__],
            ['menu' => __FILE__],
        );

        $audio->playEffect('enemy_hit');
        $audio->playMusic('menu');
        $audio->update();
        $audio->close();

        self::assertFalse($audio->isAvailable());
        self::assertSame(['init'], $device->calls);
    }

    public function test_sound_is_loaded_once_and_reused(): void
    {
        $device = new FakeAudioDevice;

        $audio = new RaylibAudio(
            $device,
            ['enemy_hit' => __FILE__],
        );

        $audio->playEffect('enemy_hit');
        $audio->playEffect('enemy_hit');
        $audio->close();
        $audio->close();

        self::assertSame(
            [
                'init',
                'loadSound',
                'playSound',
                'playSound',
                'unloadSound',
                'shutdown',
            ],
            $device->calls
        );
    }

    public function test_music_changes_without_restarting_same_track(): void
    {
        $device = new FakeAudioDevice;

        $audio = new RaylibAudio(
            $device,
            [],
            [
                'menu' => __FILE__,
                'game' => __FILE__,
            ]
        );

        $audio->playMusic('menu');
        $audio->playMusic('menu');
        $audio->update();
        $audio->playMusic('game');
        $audio->stopMusic();
        $audio->close();

        self::assertSame(
            [
                'init',
                'loadMusic',
                'playMusic',
                'updateMusic',
                'loadMusic',
                'playMusic',
                'unloadMusic',
                'unloadMusic',
                'shutdown',
            ],
            $device->calls
        );
    }

    public function test_missing_asset_is_ignored_safely(): void
    {
        $device = new FakeAudioDevice;

        $audio = new RaylibAudio(
            $device,
            ['enemy_hit' => __DIR__.'/missing.wav'],
        );

        $audio->playEffect('enemy_hit');

        self::assertNotEmpty($audio->failures());
        self::assertSame(['init'], $device->calls);

        $audio->close();
    }

    public function test_loading_failure_does_not_escape(): void
    {
        $device = new FakeAudioDevice(
            enabled: true,
            failLoad: true,
        );

        $audio = new RaylibAudio(
            $device,
            ['enemy_hit' => __FILE__],
        );

        $audio->playEffect('enemy_hit');

        self::assertNotEmpty($audio->failures());

        $audio->close();
    }

    public function test_closed_audio_does_not_restart(): void
    {
        $device = new FakeAudioDevice;

        $audio = new RaylibAudio(
            $device,
            ['enemy_hit' => __FILE__],
        );

        $audio->close();
        $audio->playEffect('enemy_hit');

        self::assertSame([], $device->calls);
    }

    public function test_events_trigger_effects_once(): void
    {
        $device = new FakeAudioDevice;

        $audio = new RaylibAudio(
            $device,
            [
                'level_up' => __FILE__,
                'player_died' => __FILE__,
                'item_picked' => __FILE__,
            ]
        );

        $bus = new EventBus;
        $subscriber = new AudioEventSubscriber($audio);

        $subscriber->register($bus);
        $subscriber->register($bus);

        $bus->publish(new LevelUp('player', 2, 10, 0.0));

        $bus->publish(
            new ItemPicked('player', 'pickup-1', 'item-1', 0.0)
        );

        $bus->publish(
            new PlayerDied('player', Vector2::zero(), null, 0.0)
        );

        self::assertSame(
            [
                'init',
                'loadSound',
                'playSound',
                'loadSound',
                'playSound',
                'loadSound',
                'playSound',
            ],
            $device->calls
        );

        $audio->close();
    }

    public function test_audio_failure_does_not_block_event_publication(): void
    {
        $audio = new class implements AudioInterface
        {
            public function playEffect(string $effect): void
            {
                throw new RuntimeException('Audio indisponivel');
            }

            public function playMusic(string $track): void {}

            public function update(): void {}

            public function stopMusic(): void {}

            public function close(): void {}
        };

        $bus = new EventBus;
        $subscriber = new AudioEventSubscriber($audio);

        $subscriber->register($bus);

        $received = 0;

        $bus->subscribe(
            LevelUp::class,
            static function (LevelUp $event) use (&$received): void {
                $received++;
            }
        );

        $bus->publish(new LevelUp('player', 2, 10, 0.0));

        self::assertSame(1, $received);
    }
}

final class FakeAudioDevice implements AudioDeviceInterface
{
    /** @var list<string> */
    public array $calls = [];

    public function __construct(
        private readonly bool $enabled = true,
        private readonly bool $failLoad = false,
    ) {}

    public function init(): bool
    {
        $this->calls[] = 'init';

        return $this->enabled;
    }

    public function loadSound(string $path): string
    {
        $this->calls[] = 'loadSound';

        if ($this->failLoad) {
            throw new RuntimeException('Falha simulada');
        }

        return 'sound-'.$path;
    }

    public function playSound(string $id): void
    {
        $this->calls[] = 'playSound';
    }

    public function unloadSound(string $id): void
    {
        $this->calls[] = 'unloadSound';
    }

    public function loadMusic(string $path): string
    {
        $this->calls[] = 'loadMusic';

        if ($this->failLoad) {
            throw new RuntimeException('Falha simulada');
        }

        return 'music-'.$path;
    }

    public function playMusic(string $id): void
    {
        $this->calls[] = 'playMusic';
    }

    public function updateMusic(string $id): void
    {
        $this->calls[] = 'updateMusic';
    }

    public function unloadMusic(string $id): void
    {
        $this->calls[] = 'unloadMusic';
    }

    public function shutdown(): void
    {
        $this->calls[] = 'shutdown';
    }
}
