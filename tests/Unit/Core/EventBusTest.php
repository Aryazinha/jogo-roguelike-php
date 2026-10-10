<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Core;

use Jogo\Core\EventBus;
use Jogo\Domain\Event\DomainEvent;
use Jogo\Domain\Event\EnemyHit;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Event\PlayerDied;
use Jogo\Domain\Value\Vector2;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EventBusTest extends TestCase
{
    public function test_publication_is_synchronous_and_follows_subscription_order(): void
    {
        $bus = new EventBus;
        $event = new PlayerDied('target', Vector2::zero(), null, 1.0);
        $trace = [];
        $bus->subscribe(PlayerDied::class, static function (PlayerDied $received) use ($event, &$trace): void {
            self::assertSame($event, $received);
            $trace[] = 'first';
        });
        $bus->subscribe(PlayerDied::class, static function (PlayerDied $received) use (&$trace): void {
            $trace[] = 'second';
        });

        $bus->publish($event);
        $trace[] = 'returned';

        self::assertSame(['first', 'second', 'returned'], $trace);
    }

    public function test_each_registration_is_called_once(): void
    {
        $bus = new EventBus;
        $calls = 0;
        $listener = static function (PlayerDied $event) use (&$calls): void {
            $calls++;
        };
        $bus->subscribe(PlayerDied::class, $listener);
        $bus->subscribe(PlayerDied::class, $listener);

        $bus->publish(new PlayerDied('target', Vector2::zero(), null, 0.0));

        self::assertSame(2, $calls);
    }

    public function test_only_the_exact_class_is_dispatched_not_the_marker_or_another_event(): void
    {
        $bus = new EventBus;
        $trace = [];
        $bus->subscribe(DomainEvent::class, static function (DomainEvent $event) use (&$trace): void {
            $trace[] = 'marker';
        });
        $bus->subscribe(EnemyHit::class, static function (EnemyHit $event) use (&$trace): void {
            $trace[] = 'hit';
        });
        $bus->subscribe(EnemyKilled::class, static function (EnemyKilled $event) use (&$trace): void {
            $trace[] = 'killed';
        });

        $bus->publish(new EnemyHit('target', 'fixture-type', Vector2::zero(), 1.0, 9.0, null, null, 0.0));

        self::assertSame(['hit'], $trace);
    }

    public function test_publication_without_listeners_is_valid(): void
    {
        $bus = new EventBus;
        $bus->publish(new PlayerDied('target', Vector2::zero(), null, 0.0));
        $bus->publish(new EnemyKilled('other', 'fixture-type', true, Vector2::zero(), null, null, 0.0));

        self::assertInstanceOf(EventBus::class, $bus);
    }

    public function test_a_leading_namespace_separator_does_not_change_class_identity(): void
    {
        $bus = new EventBus;
        $calls = 0;
        $bus->subscribe('\\'.PlayerDied::class, static function (PlayerDied $event) use (&$calls): void {
            $calls++;
        });
        $bus->publish(new PlayerDied('target', Vector2::zero(), null, 0.0));
        self::assertSame(1, $calls);
    }

    public function test_listener_errors_propagate_and_stop_the_current_publication(): void
    {
        $bus = new EventBus;
        $failure = new RuntimeException('Falha do assinante de teste.');
        $laterCalls = 0;
        $bus->subscribe(PlayerDied::class, static function (PlayerDied $event) use ($failure): void {
            throw $failure;
        });
        $bus->subscribe(PlayerDied::class, static function (PlayerDied $event) use (&$laterCalls): void {
            $laterCalls++;
        });

        try {
            $bus->publish(new PlayerDied('target', Vector2::zero(), null, 0.0));
            self::fail('A exceção do listener precisa chegar ao chamador.');
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }

        self::assertSame(0, $laterCalls);
    }

    public function test_matches_do_not_share_listeners(): void
    {
        $first = new EventBus;
        $second = new EventBus;
        $firstCalls = 0;
        $secondCalls = 0;
        $first->subscribe(PlayerDied::class, static function (PlayerDied $event) use (&$firstCalls): void {
            $firstCalls++;
        });
        $event = new PlayerDied('target', Vector2::zero(), null, 0.0);
        $second->publish($event);
        self::assertSame(0, $firstCalls);
        $second->subscribe(PlayerDied::class, static function (PlayerDied $event) use (&$secondCalls): void {
            $secondCalls++;
        });
        $first->publish($event);
        self::assertSame(1, $firstCalls);
        self::assertSame(0, $secondCalls);
        $second->publish($event);
        self::assertSame(1, $firstCalls);
        self::assertSame(1, $secondCalls);
    }

    public function test_subscriptions_during_dispatch_start_with_the_next_publication(): void
    {
        $bus = new EventBus;
        $trace = [];
        $added = false;
        $bus->subscribe(PlayerDied::class, static function (PlayerDied $event) use ($bus, &$trace, &$added): void {
            $trace[] = 'original';
            if (! $added) {
                $added = true;
                $bus->subscribe(PlayerDied::class, static function (PlayerDied $event) use (&$trace): void {
                    $trace[] = 'new';
                });
            }
        });
        $event = new PlayerDied('target', Vector2::zero(), null, 0.0);
        $bus->publish($event);
        self::assertSame(['original'], $trace);
        $bus->publish($event);
        self::assertSame(['original', 'original', 'new'], $trace);
    }
}
