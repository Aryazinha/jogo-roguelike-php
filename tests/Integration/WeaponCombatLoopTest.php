<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Core\GameLoop;
use Jogo\Domain\Event\EnemyHit;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\LogicalProjectile;
use Jogo\Domain\Weapon\TntCharge;
use Jogo\Domain\Weapon\Weapon;
use Jogo\Tests\Support\CombatHarness;
use Jogo\Tests\Support\ManualClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WeaponCombatLoopTest extends TestCase
{
    /** @return iterable<string, array{list<float>, list<string>}> */
    public static function schedules(): iterable
    {
        foreach (['sword and bow' => ['stone_sword', 'bow'], 'tnt and torch' => ['tnt', 'torch']] as $pair => $weapons) {
            foreach ([30, 60, 144, 240] as $fps) {
                yield "$pair: $fps FPS" => [[1.0 / $fps], $weapons];
            }
            yield "$pair: irregular" => [[1.0 / 120.0, 1.0 / 40.0, 1.0 / 90.0, 1.0 / 30.0, 1.0 / 60.0], $weapons];
        }
    }

    /**
     * @param  list<float>  $frames
     * @param  list<string>  $weapons
     */
    #[DataProvider('schedules')]
    public function test_entire_combat_trace_is_identical_across_frame_rates_and_irregular_frames(array $frames, array $weapons): void
    {
        $baseline = self::simulate([1.0 / 60.0], $weapons);
        $actual = self::simulate($frames, $weapons);
        self::assertCount(180, $actual['states']);
        self::assertNotEmpty($actual['hits']);
        self::assertSame($baseline, $actual);
        foreach ($weapons as $weaponId) {
            self::assertContains($weaponId, array_column($actual['hits'], 'weaponId'));
        }
    }

    public function test_pause_freezes_projectiles_charges_and_both_cooldowns_and_resume_excludes_real_time(): void
    {
        $h = new CombatHarness;
        $h->world->add('target', 200.0, radius: 2.0);
        $h->equip('bow');
        $h->equip('tnt');
        $clock = new ManualClock;
        $step = 0;
        $renders = 0;
        $loop = new GameLoop($clock,
            static function (float $dt) use ($h, &$step): void {
                $step++;
                $h->step($dt, $step * GameLoop::STEP_SECONDS);
            },
            static function (float $alpha) use (&$renders): void {
                $renders++;
            });
        for ($index = 0; $index < 2; $index++) {
            $clock->advance(GameLoop::STEP_SECONDS);
            $loop->tick();
        }
        $projectile = $h->combat->projectiles()[0];
        $charge = $h->combat->charges()[0];
        $position = $projectile->position();
        $remaining = $charge->remainingSeconds();
        $cooldowns = array_map(static fn (Weapon $weapon): float => $weapon->remainingSeconds(), $h->inventory->weapons());
        $loop->pause();
        $clock->advance(600.0);
        self::assertSame(0, $loop->tick());
        self::assertSame(2, $step);
        self::assertSame($position, $projectile->position());
        self::assertSame($remaining, $charge->remainingSeconds());
        self::assertSame($cooldowns, array_map(static fn (Weapon $weapon): float => $weapon->remainingSeconds(), $h->inventory->weapons()));
        self::assertCount(0, $h->hits);
        self::assertSame(3, $renders);
        $loop->resume();
        self::assertSame(0, $loop->tick());
        $clock->advance(GameLoop::STEP_SECONDS);
        self::assertSame(1, $loop->tick());
        self::assertSame(3, $step);
        self::assertSame(14.0, $projectile->position()->x());
        self::assertEqualsWithDelta($remaining - GameLoop::STEP_SECONDS, $charge->remainingSeconds(), 1.0E-12);
        for ($index = 0; $index < 58; $index++) {
            $clock->advance(GameLoop::STEP_SECONDS);
            $loop->tick();
        }
        self::assertSame(61, $step);
        self::assertTrue($charge->isDetonated());
        self::assertSame(['bow', 'tnt'], array_column($h->hits, 'weaponId'));
        self::assertSame(61 * GameLoop::STEP_SECONDS, $h->hits[1]->elapsedSeconds);
    }

    public function test_events_follow_slot_then_target_order_with_hit_immediately_before_its_death(): void
    {
        $h = new CombatHarness;
        $h->world->add('b', 20.0);
        $h->world->add('a', 10.0, health: 2.0);
        $h->equip('stone_sword');
        $h->equip('torch');
        $order = [];
        $h->events->subscribe(EnemyHit::class, static function (EnemyHit $event) use (&$order): void {
            $order[] = 'hit:'.$event->enemyId.':'.$event->weaponId;
        });
        $h->events->subscribe(EnemyKilled::class, static function (EnemyKilled $event) use (&$order, $h): void {
            $order[] = 'death:'.$event->enemyId;
            self::assertFalse($h->world->targets[$event->enemyId]->isActive());
        });
        $h->step(GameLoop::STEP_SECONDS, GameLoop::STEP_SECONDS);
        self::assertSame(['hit:a:stone_sword', 'death:a', 'hit:b:stone_sword', 'hit:b:torch'], $order);
    }

    /**
     * @param  list<float>  $frames
     * @param  list<string>  $weapons
     * @return array{states: list<array<mixed>>, hits: list<array<string, mixed>>, deaths: list<array<string, mixed>>}
     */
    private static function simulate(array $frames, array $weapons): array
    {
        $h = new CombatHarness;
        $h->world->add('enemy-z', 150.0, 20.0, 2.0);
        $h->world->add('enemy-a', 60.0, 0.0, 2.0);
        $h->world->add('enemy-b', -20.0, 0.0, 2.0);
        foreach ($weapons as $id) {
            $h->equip($id);
        }
        $clock = new ManualClock;
        $step = 0;
        $states = [];
        $loop = new GameLoop($clock,
            static function (float $dt) use ($h, &$step, &$states): void {
                $step++;
                if ($step === 30) {
                    $h->world->targets['enemy-a']->moveTo(new Vector2(60.0, 10.0));
                }
                if ($step === 90) {
                    $h->world->facing = new Vector2(0.0, -1.0);
                }
                if ($step === 120) {
                    $h->world->origin = new Vector2(10.0, 0.0);
                }
                $h->step($dt, $step * GameLoop::STEP_SECONDS);
                $states[] = [
                    array_map(static fn ($entity): float => $entity->health()->current(), $h->world->targets),
                    array_map(static fn (Weapon $weapon): float => $weapon->remainingSeconds(), $h->inventory->weapons()),
                    array_map(static fn (LogicalProjectile $p): array => [$p->attack->id, $p->attack->definition->id,
                        $p->position()->x(), $p->position()->y(), $p->traveled(), $p->target->entityId], $h->combat->projectiles()),
                    array_map(static fn (TntCharge $c): array => [$c->attack->id, $c->position->x(), $c->position->y(), $c->remainingSeconds()], $h->combat->charges()),
                ];
            }, static function (float $alpha): void {});
        $total = 0.0;
        $frame = 0;
        while ($total < 3.0) {
            $dt = min($frames[$frame % count($frames)], 3.0 - $total);
            $clock->advance($dt);
            $total += $dt;
            $loop->tick();
            $frame++;
        }

        return ['states' => $states,
            'hits' => array_map(static fn (EnemyHit $hit): array => ['enemyId' => $hit->enemyId, 'weaponId' => $hit->weaponId,
                'source' => $hit->sourceEntityId, 'applied' => $hit->appliedDamage, 'remaining' => $hit->remainingHealth,
                'time' => $hit->elapsedSeconds, 'position' => [$hit->position->x(), $hit->position->y()]], $h->hits),
            'deaths' => array_map(static fn (EnemyKilled $death): array => ['id' => $death->enemyId, 'time' => $death->elapsedSeconds], $h->deaths)];
    }
}
