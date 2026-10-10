<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\System;

use InvalidArgumentException;
use Jogo\Core\EventBus;
use Jogo\Domain\Event\DomainEvent;
use Jogo\Domain\Event\EnemyHit;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Event\PlayerDamaged;
use Jogo\Domain\Event\PlayerDied;
use Jogo\Domain\System\DamageSystem;
use Jogo\Domain\Value\Damage;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DamageSystemTest extends TestCase
{
    /** @return iterable<string, array{bool}> */
    public static function targetRoles(): iterable
    {
        yield 'enemy' => [true];
        yield 'player' => [false];
    }

    #[DataProvider('targetRoles')]
    public function test_partial_damage_publishes_the_complete_payload_from_actual_health(bool $enemy): void
    {
        $bus = new EventBus;
        $events = [];
        self::recordEvents($bus, $events);
        $target = self::target();
        $position = $target->position();
        $system = new DamageSystem($bus);

        self::assertSame(2.5, self::hit($enemy, $system, $target, new Damage(2.5, 'attacker', 'fixture-weapon'), 4.25));
        self::assertSame(7.5, $target->health()->current());
        self::assertTrue($target->isActive());
        self::assertCount(1, $events);
        $event = $events[0];
        if ($enemy) {
            self::assertInstanceOf(EnemyHit::class, $event);
            self::assertSame('target', $event->enemyId);
            self::assertSame('fixture-type', $event->enemyTypeId);
            self::assertSame('fixture-weapon', $event->weaponId);
        } else {
            self::assertInstanceOf(PlayerDamaged::class, $event);
            self::assertSame('target', $event->playerId);
        }
        self::assertSame('attacker', $event->sourceEntityId);
        self::assertSame(2.5, $event->appliedDamage);
        self::assertSame(7.5, $event->remainingHealth);
        self::assertSame(4.25, $event->elapsedSeconds);
        self::assertTrue($position->equals($event->position, 0.0));
        self::assertNotSame($position, $event->position);
    }

    #[DataProvider('targetRoles')]
    public function test_fatal_damage_hit_then_deactivation_then_death_with_complete_payload(bool $enemy): void
    {
        $bus = new EventBus;
        $events = [];
        self::recordEvents($bus, $events);
        $target = self::target();
        $trace = [];
        $bus->subscribe($enemy ? EnemyHit::class : PlayerDamaged::class,
            static function (DomainEvent $event) use ($target, &$trace): void {
                $trace[] = ['hit', $target->health()->current(), $target->isActive()];
            });
        $bus->subscribe($enemy ? EnemyKilled::class : PlayerDied::class,
            static function (DomainEvent $event) use ($target, &$trace): void {
                $trace[] = ['death', $target->health()->current(), $target->isActive()];
            });
        $system = new DamageSystem($bus);

        self::assertSame(10.0, self::hit($enemy, $system, $target, new Damage(10.0, 'attacker', 'fixture-weapon'), 2.75));
        self::assertSame([['hit', 0.0, true], ['death', 0.0, false]], $trace);
        self::assertSame([$enemy ? EnemyHit::class : PlayerDamaged::class, $enemy ? EnemyKilled::class : PlayerDied::class],
            array_map(static fn (DomainEvent $event): string => $event::class, $events));
        $hit = $events[0];
        $death = $events[1];
        if ($enemy) {
            self::assertInstanceOf(EnemyHit::class, $hit);
            self::assertInstanceOf(EnemyKilled::class, $death);
            self::assertSame('target', $death->enemyId);
            self::assertSame('fixture-type', $death->enemyTypeId);
            self::assertFalse($death->isBoss);
            self::assertSame('fixture-weapon', $death->weaponId);
        } else {
            self::assertInstanceOf(PlayerDamaged::class, $hit);
            self::assertInstanceOf(PlayerDied::class, $death);
            self::assertSame('target', $death->playerId);
        }
        self::assertSame(10.0, $hit->appliedDamage);
        self::assertSame(0.0, $hit->remainingHealth);
        self::assertSame('attacker', $death->sourceEntityId);
        self::assertSame(2.75, $hit->elapsedSeconds);
        self::assertSame($hit->elapsedSeconds, $death->elapsedSeconds);
        self::assertSame($hit->position, $death->position);
        for ($i = 0; $i < 10; $i++) {
            self::assertSame(0.0, self::hit($enemy, $system, $target, new Damage(100.0), 3.0 + $i));
        }
        self::assertCount(2, $events);
    }

    #[DataProvider('targetRoles')]
    public function test_overkill_reports_only_the_hp_actually_removed(bool $enemy): void
    {
        $bus = new EventBus;
        $events = [];
        self::recordEvents($bus, $events);
        $target = self::target();

        self::assertSame(10.0, self::hit($enemy, new DamageSystem($bus), $target, new Damage(PHP_FLOAT_MAX), 0.0));
        $hit = $events[0];
        self::assertTrue($hit instanceof EnemyHit || $hit instanceof PlayerDamaged);
        self::assertSame(10.0, $hit->appliedDamage);
        self::assertSame(0.0, $hit->remainingHealth);
        self::assertSame(0.0, $target->health()->current());
    }

    /** @return iterable<string, array{bool, string}> */
    public static function ignoredDamage(): iterable
    {
        foreach (self::targetRoles() as $role => [$enemy]) {
            foreach (['zero', 'inactive', 'dead'] as $state) {
                yield "$role:$state" => [$enemy, $state];
            }
        }
    }

    #[DataProvider('ignoredDamage')]
    public function test_zero_inactive_or_already_dead_targets_do_not_publish_events(bool $enemy, string $state): void
    {
        $bus = new EventBus;
        $events = [];
        self::recordEvents($bus, $events);
        $target = self::target(current: $state === 'dead' ? 0.0 : 10.0);
        if ($state === 'inactive') {
            $target->deactivate();
        }
        $current = $target->health()->current();

        self::assertSame(0.0, self::hit($enemy, new DamageSystem($bus), $target, new Damage($state === 'zero' ? 0.0 : 5.0), 1.0));
        self::assertSame($current, $target->health()->current());
        self::assertSame([], $events);
        if ($state === 'dead') {
            self::assertFalse($target->isActive());
        }
    }

    #[DataProvider('targetRoles')]
    public function test_death_is_unique_by_id_even_for_another_object_or_target_role(bool $enemy): void
    {
        $bus = new EventBus;
        $events = [];
        self::recordEvents($bus, $events);
        $system = new DamageSystem($bus);
        $target = self::target(id: '0');
        self::hit($enemy, $system, $target, new Damage(10.0), 1.0);
        // Reutilizar ID viola o contrato; mesmo assim não pode produzir uma segunda morte.
        $replacement = self::target(id: '0');
        self::assertSame(0.0, self::hit(! $enemy, $system, $replacement, new Damage(10.0), 2.0));
        self::assertSame(10.0, $replacement->health()->current());
        $target->health()->heal(10.0);
        self::assertSame(0.0, self::hit($enemy, $system, $target, new Damage(10.0), 2.0));
        self::assertCount(2, $events);

        $newMatch = new DamageSystem(new EventBus);
        self::assertSame(10.0, self::hit($enemy, $newMatch, $replacement, new Damage(10.0), 0.0));
        self::assertFalse($replacement->isActive());
    }

    #[DataProvider('targetRoles')]
    public function test_fatal_snapshot_survives_movement_by_a_listener(bool $enemy): void
    {
        $bus = new EventBus;
        $events = [];
        self::recordEvents($bus, $events);
        $target = self::target();
        $original = $target->position();
        $bus->subscribe($enemy ? EnemyHit::class : PlayerDamaged::class,
            static function (DomainEvent $event) use ($target): void {
                $target->moveTo(new Vector2(900.0, 800.0));
            });

        self::hit($enemy, new DamageSystem($bus), $target, new Damage(10.0), 7.125);
        foreach ($events as $event) {
            self::assertTrue($event instanceof EnemyHit || $event instanceof EnemyKilled
                || $event instanceof PlayerDamaged || $event instanceof PlayerDied);
            self::assertTrue($original->equals($event->position, 0.0));
            self::assertSame(7.125, $event->elapsedSeconds);
        }
        self::assertSame(900.0, $target->position()->x());
    }

    #[DataProvider('targetRoles')]
    public function test_unknown_source_and_absent_weapon_remain_null(bool $enemy): void
    {
        $bus = new EventBus;
        $events = [];
        self::recordEvents($bus, $events);
        self::hit($enemy, new DamageSystem($bus), self::target(), new Damage(10.0), 0.0);
        foreach ($events as $event) {
            self::assertTrue($event instanceof EnemyHit || $event instanceof EnemyKilled
                || $event instanceof PlayerDamaged || $event instanceof PlayerDied);
            self::assertNull($event->sourceEntityId);
            if ($event instanceof EnemyHit || $event instanceof EnemyKilled) {
                self::assertNull($event->weaponId);
            }
        }
    }

    public function test_boss_classification_is_supplied_explicitly_without_inferring_content(): void
    {
        $bus = new EventBus;
        $events = [];
        self::recordEvents($bus, $events);
        $system = new DamageSystem($bus);
        $system->damageEnemy(self::target(id: 'ordinary-id'), new Damage(10.0), 'ordinary-type', true, 1.0);
        $system->damageEnemy(self::target(id: 'boss-looking-id'), new Damage(10.0), 'boss-looking-type', false, 1.0);

        self::assertCount(4, $events);
        self::assertInstanceOf(EnemyKilled::class, $events[1]);
        self::assertTrue($events[1]->isBoss);
        self::assertInstanceOf(EnemyKilled::class, $events[3]);
        self::assertFalse($events[3]->isBoss);
        self::assertNotContains(PlayerDied::class, array_map(static fn (DomainEvent $event): string => $event::class, $events));
    }

    #[DataProvider('targetRoles')]
    public function test_partial_and_fatal_damage_work_without_listeners(bool $enemy): void
    {
        $system = new DamageSystem(new EventBus);
        $target = self::target();
        self::assertSame(1.5, self::hit($enemy, $system, $target, new Damage(1.5), 0.0));
        self::assertSame(8.5, self::hit($enemy, $system, $target, new Damage(100.0), 1.0));
        self::assertFalse($target->isActive());
        self::assertSame(0.0, self::hit($enemy, $system, $target, new Damage(1.0), 2.0));
    }

    /** @return iterable<string, array{bool, float}> */
    public static function invalidTimes(): iterable
    {
        foreach (self::targetRoles() as $role => [$enemy]) {
            foreach (['negative' => -1.0, 'inf' => INF, 'negative inf' => -INF, 'nan' => NAN] as $name => $time) {
                yield "$role:$name" => [$enemy, $time];
            }
        }
    }

    #[DataProvider('invalidTimes')]
    public function test_invalid_time_fails_before_mutating_health_or_publishing(bool $enemy, float $time): void
    {
        $bus = new EventBus;
        $events = [];
        self::recordEvents($bus, $events);
        $target = self::target();
        try {
            self::hit($enemy, new DamageSystem($bus), $target, new Damage(5.0), $time);
            self::fail('Tempo inválido precisa falhar.');
        } catch (InvalidArgumentException) {
            self::assertSame(10.0, $target->health()->current());
            self::assertTrue($target->isActive());
            self::assertSame([], $events);
        }
    }

    public function test_invalid_enemy_type_fails_before_damage(): void
    {
        $target = self::target();
        try {
            (new DamageSystem(new EventBus))->damageEnemy($target, new Damage(5.0), '  ', false, 0.0);
            self::fail('Tipo vazio precisa falhar.');
        } catch (InvalidArgumentException) {
            self::assertSame(10.0, $target->health()->current());
        }
    }

    public function test_invalid_time_is_not_hidden_by_an_inactive_target(): void
    {
        $target = self::target();
        $target->deactivate();
        $this->expectException(InvalidArgumentException::class);
        (new DamageSystem(new EventBus))->damagePlayer($target, new Damage(0.0), INF);
    }

    #[DataProvider('targetRoles')]
    public function test_fatal_id_is_reserved_before_the_hit_listeners_run(bool $enemy): void
    {
        $bus = new EventBus;
        $events = [];
        self::recordEvents($bus, $events);
        $system = new DamageSystem($bus);
        $replacement = self::target();
        $bus->subscribe($enemy ? EnemyHit::class : PlayerDamaged::class,
            static function (DomainEvent $event) use ($enemy, $system, $replacement): void {
                self::assertSame(0.0, self::hit(! $enemy, $system, $replacement, new Damage(10.0), 1.0));
            });

        self::hit($enemy, $system, self::target(), new Damage(10.0), 1.0);
        self::assertCount(2, $events);
        self::assertSame(10.0, $replacement->health()->current());
    }

    #[DataProvider('targetRoles')]
    public function test_fatal_hit_listener_error_propagates_but_still_deactivates_and_prevents_replay(bool $enemy): void
    {
        $bus = new EventBus;
        $events = [];
        self::recordEvents($bus, $events);
        $failure = new RuntimeException('Falha no feedback de dano.');
        $bus->subscribe($enemy ? EnemyHit::class : PlayerDamaged::class,
            static function (DomainEvent $event) use ($failure): void {
                throw $failure;
            });
        $system = new DamageSystem($bus);
        $target = self::target();
        try {
            self::hit($enemy, $system, $target, new Damage(10.0), 1.0);
            self::fail('A exceção não pode ser silenciada.');
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
        self::assertSame(0.0, $target->health()->current());
        self::assertFalse($target->isActive());
        self::assertCount(1, $events);
        self::assertSame(0.0, self::hit($enemy, $system, self::target(), new Damage(10.0), 2.0));
        self::assertCount(1, $events);
    }

    #[DataProvider('targetRoles')]
    public function test_death_listener_error_cannot_cause_a_second_death_publication(bool $enemy): void
    {
        $bus = new EventBus;
        $events = [];
        self::recordEvents($bus, $events);
        $failure = new RuntimeException('Falha no feedback de morte.');
        $bus->subscribe($enemy ? EnemyKilled::class : PlayerDied::class,
            static function (DomainEvent $event) use ($failure): void {
                throw $failure;
            });
        $system = new DamageSystem($bus);
        $target = self::target();
        try {
            self::hit($enemy, $system, $target, new Damage(10.0), 1.0);
            self::fail('A exceção não pode ser silenciada.');
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
        self::assertFalse($target->isActive());
        self::assertCount(2, $events);
        self::assertSame(0.0, self::hit($enemy, $system, self::target(), new Damage(10.0), 2.0));
        self::assertCount(2, $events);
    }

    private static function target(string $id = 'target', float $current = 10.0): FakeDamageable
    {
        return new FakeDamageable($id, new Vector2(-3.0, 4.0), 2.0, new Health(10.0, $current));
    }

    private static function hit(bool $enemy, DamageSystem $system, FakeDamageable $target, Damage $damage, float $time): float
    {
        return $enemy
            ? $system->damageEnemy($target, $damage, 'fixture-type', false, $time)
            : $system->damagePlayer($target, $damage, $time);
    }

    /** @param list<DomainEvent> $events */
    private static function recordEvents(EventBus $bus, array &$events): void
    {
        foreach ([EnemyHit::class, EnemyKilled::class, PlayerDamaged::class, PlayerDied::class] as $class) {
            $bus->subscribe($class, static function (DomainEvent $event) use (&$events): void {
                $events[] = $event;
            });
        }
    }
}
