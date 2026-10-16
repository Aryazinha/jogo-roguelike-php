<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\System;

use Jogo\Core\EventBus;
use Jogo\Domain\Enemy\Boss\BossPhase;
use Jogo\Domain\Enemy\Boss\BossPhaseSettings;
use Jogo\Domain\Enemy\EnemyCatalog;
use Jogo\Domain\Enemy\EnemyFactory;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\System\BossSystem;
use Jogo\Domain\System\DamageSystem;
use Jogo\Domain\System\MovementSystem;
use Jogo\Domain\Value\Damage;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\MapLayout;
use Jogo\Domain\World\SpawnPointPicker;
use Jogo\Tests\Support\ControlledRandomIndex;
use Jogo\Tests\Support\EnemyFixtures;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

final class BossSystemTest extends TestCase
{
    private EventBus $bus;

    public function test_boss_appears_at_its_time_and_only_once(): void
    {
        [$world, $system] = $this->setUpBoss();

        $system->update(1 / 60, 599.9);
        self::assertNull($system->boss());

        $system->update(1 / 60, 600.0);
        $system->update(1 / 60, 600.1);

        self::assertSame(['warden'], array_map(static fn ($e) => $e->typeId(), $world->enemies()));
        self::assertSame(BossPhase::CHASING, $system->phase());
        // Ângulo 0 e distância mínima do anel: 500 px à direita do jogador.
        self::assertEqualsWithDelta(500.0, $world->enemies()[0]->position()->distanceTo($world->player()->position()), 2.0);
    }

    public function test_chasing_boss_walks_toward_the_player(): void
    {
        [$world, $system] = $this->setUpBoss();
        $system->update(1 / 60, 600.0);
        $boss = $system->boss();
        self::assertNotNull($boss);
        $before = $boss->position()->distanceTo($world->player()->position());

        $system->update(1.0, 601.0);

        self::assertEqualsWithDelta($before - 45.0, $boss->position()->distanceTo($world->player()->position()), 1e-6);
    }

    public function test_charge_runs_three_times_faster_along_the_locked_line(): void
    {
        [$world, $system] = $this->setUpBoss();
        $system->update(1 / 60, 600.0);
        $boss = $system->boss();
        self::assertNotNull($boss);
        $elapsed = 600.0;
        while ($system->phase() !== BossPhase::CHARGING) {
            $elapsed += 1 / 60;
            $system->update(1 / 60, $elapsed);
        }
        $start = $boss->position();

        $system->update(1 / 60, $elapsed + 1 / 60);

        self::assertEqualsWithDelta(45.0 * 3.0 / 60, $boss->position()->distanceTo($start), 1e-6);
    }

    public function test_crossing_seventy_percent_queues_eight_zombies_around_the_boss(): void
    {
        [$world, $system] = $this->setUpBoss();
        $system->update(1 / 60, 600.0);
        $boss = $system->boss();
        self::assertNotNull($boss);
        $boss->health()->damage(300.0);

        $system->update(1 / 60, 600.1);
        self::assertSame(BossPhase::SUMMONING, $system->phase());
        self::assertCount(1, $world->enemies(), 'invocados só entram no flush');
        $world->flush();

        $summoned = array_slice($world->enemies(), 1);
        self::assertCount(8, $summoned);
        foreach ($summoned as $zombie) {
            self::assertSame('zombie', $zombie->typeId());
            self::assertEqualsWithDelta(76.0, $zombie->position()->distanceTo($boss->position()), 1e-6);
        }
    }

    public function test_defeat_is_recorded_from_the_boss_kill_event(): void
    {
        [$world, $system] = $this->setUpBoss();
        $system->update(1 / 60, 600.0);
        $boss = $system->boss();
        self::assertNotNull($boss);
        $damage = new DamageSystem($this->bus);
        $kills = [];
        $this->bus->subscribe(EnemyKilled::class, static function (EnemyKilled $event) use (&$kills): void {
            $kills[] = $event->isBoss;
        });

        $target = $boss->asTarget();
        $damage->damageEnemy($boss, new Damage(5000.0), $target->enemyTypeId, $target->isBoss, 642.5);
        $system->update(1 / 60, 642.6);

        self::assertSame([true], $kills);
        self::assertTrue($system->isDefeated());
        self::assertSame(642.5, $system->defeatedAt());
    }

    public function test_common_enemy_death_is_not_a_victory(): void
    {
        [$world, $system] = $this->setUpBoss();
        $system->update(1 / 60, 600.0);
        $zombie = (new EnemyFactory(new EnemyCatalog(EnemyFixtures::fullSet()), 'other'))->create('zombie', new Vector2(900.0, 900.0));
        $world->addEnemy($zombie);

        (new DamageSystem($this->bus))->damageEnemy($zombie, new Damage(100.0), 'zombie', false, 601.0);

        self::assertFalse($system->isDefeated());
    }

    public function test_pause_keeps_the_boss_still(): void
    {
        [, $system] = $this->setUpBoss();
        $system->update(1 / 60, 600.0);
        $boss = $system->boss();
        self::assertNotNull($boss);
        $position = $boss->position();

        $system->update(0.0, 600.0);

        self::assertTrue($boss->position()->equals($position));
    }

    /** @return array{GameWorld, BossSystem} */
    private function setUpBoss(): array
    {
        $this->bus = new EventBus;
        $player = new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(20.0));
        $layout = new MapLayout(2000.0, 2000.0, 32.0, 500.0, 700.0);
        $world = new GameWorld($player, $layout);
        $factory = new EnemyFactory(new EnemyCatalog(EnemyFixtures::fullSet()));
        $settings = new BossPhaseSettings(0.8, 1.0, 3.0, 1.0, 8.0, [0.7, 0.4], 8, 'zombie', 1.0, 0.25, 0.3, -0.3);
        $system = new BossSystem($world, $factory, $settings, new SpawnPointPicker($layout, new ControlledRandomIndex(0)),
            new MovementSystem($layout->bounds()), 'warden', 600.0);
        $system->subscribe($this->bus);

        return [$world, $system];
    }
}
