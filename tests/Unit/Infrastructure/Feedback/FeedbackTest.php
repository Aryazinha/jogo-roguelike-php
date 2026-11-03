<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Infrastructure\Feedback;

use InvalidArgumentException;
use Jogo\Core\EventBus;
use Jogo\Domain\Event\EnemyHit;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Event\ItemPicked;
use Jogo\Domain\Event\LevelUp;
use Jogo\Domain\Event\PlayerDamaged;
use Jogo\Domain\Event\PlayerDied;
use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Audio\AudioEventSubscriber;
use Jogo\Infrastructure\Audio\AudioInterface;
use Jogo\Infrastructure\Feedback\ChestOpenedNotice;
use Jogo\Infrastructure\Feedback\FeedbackSubscribers;
use Jogo\Infrastructure\Feedback\FeedbackTypes;
use Jogo\Infrastructure\Feedback\VisualFeedbackPool;
use Jogo\Infrastructure\Feedback\VisualFeedbackSubscriber;
use Jogo\Infrastructure\Play\PlayFrameFactory;
use Jogo\Infrastructure\Play\PlayHudSnapshot;
use Jogo\Infrastructure\Play\PlaySnapshot;
use Jogo\Infrastructure\Play\PlayWorldSnapshot;
use Jogo\Tests\Support\ManualClock;
use PHPUnit\Framework\TestCase;

final class FeedbackTest extends TestCase
{
    public function test_all_seven_events_trigger_audio_and_visual_once(): void
    {
        $clock = new ManualClock;
        $pool = new VisualFeedbackPool($clock);
        $audio = new FeedbackAudioSpy;
        $events = new EventBus;

        $subscribers = new FeedbackSubscribers(
            new AudioEventSubscriber($audio),
            new VisualFeedbackSubscriber(
                $pool,
                static fn (): Vector2 => new Vector2(100, 100),
            ),
        );

        $subscribers->register($events);
        $subscribers->register($events);

        $position = new Vector2(150, 180);

        $events->publish(
            new PlayerDamaged('player', $position, 3, 17, null, 1)
        );

        $events->publish(
            new EnemyHit(
                'enemy-1',
                'zombie',
                $position,
                5,
                8,
                'player',
                'stone_sword',
                1
            )
        );

        $events->publish(
            new EnemyKilled(
                'enemy-1',
                'zombie',
                false,
                $position,
                'player',
                'stone_sword',
                1
            )
        );

        $events->publish(new LevelUp('player', 2, 10, 1));

        $events->publish(
            new ChestOpenedNotice('chest-1', $position, 1)
        );

        $events->publish(
            new PlayerDied('player', $position, null, 1)
        );

        $events->publish(
            new ItemPicked('player', 'pickup-1', 'apple', 1)
        );

        self::assertSame(FeedbackTypes::ALL, $audio->effects);
        self::assertSame(7, $pool->activeCount());

        $frame = $pool->frame();

        self::assertCount(35, $frame->items);
        self::assertCount(7, $frame->labels);

        self::assertSame('-3', $frame->labels[0]->text);
        self::assertSame('-5', $frame->labels[1]->text);
        self::assertSame('BAU ABERTO!', $frame->labels[4]->text);
    }

    public function test_effects_expire_on_visual_clock(): void
    {
        $clock = new ManualClock;
        $pool = new VisualFeedbackPool($clock, 4);

        $pool->emit('level_up', Vector2::zero());

        self::assertSame(1, $pool->activeCount());

        $clock->advance(1.5);

        self::assertSame(0, $pool->activeCount());
        self::assertCount(0, $pool->frame()->items);
    }

    public function test_pool_never_exceeds_capacity(): void
    {
        $clock = new ManualClock;
        $pool = new VisualFeedbackPool($clock, 2);

        for ($i = 0; $i < 20; $i++) {
            $pool->emit(
                'enemy_hit',
                new Vector2((float) $i, 10.0),
                2.0
            );
        }

        self::assertSame(2, $pool->capacity());
        self::assertSame(2, $pool->activeCount());

        $pool->clear();

        self::assertSame(0, $pool->activeCount());
    }

    public function test_feedback_is_added_without_changing_world(): void
    {
        $clock = new ManualClock;
        $pool = new VisualFeedbackPool($clock);

        $pool->emit(
            'item_picked',
            new Vector2(150, 100)
        );

        $position = new Vector2(100, 100);

        $snapshot = new PlaySnapshot(
            new PlayWorldSnapshot($position, $position),
            new PlayHudSnapshot(20, 20, 0, 10, 1, 0)
        );

        $before = serialize($snapshot);

        $frame = (new PlayFrameFactory($pool))->create($snapshot);

        self::assertSame($before, serialize($snapshot));
        self::assertCount(6, $frame->items);
        self::assertCount(1, $frame->feedbackLabels);
        self::assertSame('ITEM!', $frame->feedbackLabels[0]->text);
    }

    public function test_invalid_feedback_is_rejected(): void
    {
        $pool = new VisualFeedbackPool(new ManualClock);

        $this->expectException(InvalidArgumentException::class);

        $pool->emit('unknown', Vector2::zero());
    }

    public function test_invalid_damage_is_rejected(): void
    {
        $pool = new VisualFeedbackPool(new ManualClock);

        $this->expectException(InvalidArgumentException::class);

        $pool->emit('enemy_hit', Vector2::zero(), -3.0);
    }

    public function test_invalid_chest_notice_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ChestOpenedNotice('', Vector2::zero(), 0.0);
    }

    public function test_all_feedback_types_have_colors_and_labels(): void
    {
        foreach (FeedbackTypes::ALL as $kind) {
            self::assertCount(3, FeedbackTypes::color($kind));
            self::assertNotSame('', FeedbackTypes::label($kind));
        }
    }
}

final class FeedbackAudioSpy implements AudioInterface
{
    /** @var list<string> */
    public array $effects = [];

    public function playEffect(string $effect): void
    {
        $this->effects[] = $effect;
    }

    public function playMusic(string $track): void {}

    public function update(): void {}

    public function stopMusic(): void {}

    public function close(): void {}
}
