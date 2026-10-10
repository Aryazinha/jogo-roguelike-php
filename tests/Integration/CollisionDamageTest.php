<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Core\EventBus;
use Jogo\Core\GameLoop;
use Jogo\Domain\Event\DomainEvent;
use Jogo\Domain\Event\EnemyHit;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Event\PlayerDamaged;
use Jogo\Domain\Event\PlayerDied;
use Jogo\Domain\Spatial\SpatialHash;
use Jogo\Domain\System\CollisionSystem;
use Jogo\Domain\System\DamageSystem;
use Jogo\Domain\Value\Damage;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Tests\Support\FakeCollidable;
use Jogo\Tests\Support\FakeDamageable;
use Jogo\Tests\Support\ManualClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CollisionDamageTest extends TestCase
{
    /** @return iterable<string, array{bool}> */
    public static function targetRoles(): iterable
    {
        yield 'enemy' => [true];
        yield 'player' => [false];
    }

    #[DataProvider('targetRoles')]
    public function test_confirmed_collision_can_feed_resolved_damage_without_concrete_content(bool $enemy): void
    {
        $attacker = new FakeCollidable('attacker', new Vector2(63.0, 32.0), 2.0);
        $target = new FakeDamageable('target', new Vector2(67.0, 32.0), 2.0, new Health(5.0));
        $separated = new FakeDamageable('separated', new Vector2(90.0, 32.0), 2.0, new Health(5.0));
        $inactive = new FakeDamageable('inactive', $target->position(), 2.0, new Health(5.0));
        $inactive->deactivate();
        $grid = new SpatialHash;
        $collision = new CollisionSystem;
        $bus = new EventBus;
        $events = [];
        foreach ([EnemyHit::class, EnemyKilled::class, PlayerDamaged::class, PlayerDied::class] as $class) {
            $bus->subscribe($class, static function (DomainEvent $event) use (&$events): void {
                $events[] = $event;
            });
        }
        $system = new DamageSystem($bus);
        $grid->rebuild([$attacker, $target, $separated, $inactive]);
        $contacts = $grid->queryRadius($attacker->position(), $attacker->collisionRadius(), $attacker->id());
        self::assertSame([$target], $contacts->entities);
        foreach ($contacts->entities as $contact) {
            self::assertInstanceOf(FakeDamageable::class, $contact);
            self::assertTrue($collision->intersects($attacker, $contact));
            $damage = new Damage(100.0, $attacker->id(), $enemy ? 'fixture-weapon' : null);
            $applied = $enemy
                ? $system->damageEnemy($contact, $damage, 'fixture-type', false, 2.5)
                : $system->damagePlayer($contact, $damage, 2.5);
            self::assertSame(5.0, $applied);
        }
        self::assertSame([$enemy ? EnemyHit::class : PlayerDamaged::class, $enemy ? EnemyKilled::class : PlayerDied::class],
            array_map(static fn (DomainEvent $event): string => $event::class, $events));
        self::assertFalse($target->isActive());
        self::assertSame(5.0, $separated->health()->current());
        self::assertSame(5.0, $inactive->health()->current());
        self::assertFalse($collision->intersects($attacker, $target));
        self::assertSame([], $grid->queryRadius($attacker->position(), $attacker->collisionRadius(), $attacker->id())->entities);
    }

    public function test_combat_uses_fixed_loop_simulation_time_and_pause_adds_no_damage(): void
    {
        $clock = new ManualClock;
        $bus = new EventBus;
        $times = [];
        $bus->subscribe(PlayerDamaged::class, static function (PlayerDamaged $event) use (&$times): void {
            $times[] = $event->elapsedSeconds;
        });
        $system = new DamageSystem($bus);
        $target = new FakeDamageable('target', Vector2::zero(), 1.0, new Health(10.0));
        $loop = null;
        $loop = new GameLoop($clock,
            static function (float $dt) use ($system, $target, &$loop): void {
                self::assertInstanceOf(GameLoop::class, $loop);
                // Ataques discretos de teste; não define frequência real de contato.
                $system->damagePlayer($target, new Damage(1.0), $loop->elapsedSeconds());
            },
            static function (float $alpha): void {},
        );
        $clock->advance(GameLoop::STEP_SECONDS);
        self::assertSame(1, $loop->tick());
        $loop->pause();
        $clock->advance(30.0);
        self::assertSame(0, $loop->tick());
        self::assertCount(1, $times);
        $loop->resume();
        $clock->advance(GameLoop::STEP_SECONDS);
        self::assertSame(1, $loop->tick());
        self::assertSame([GameLoop::STEP_SECONDS, 2.0 * GameLoop::STEP_SECONDS], $times);
        self::assertSame(8.0, $target->health()->current());
    }
}
