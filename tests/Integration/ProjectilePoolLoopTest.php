<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Core\GameLoop;
use Jogo\Domain\Value\ModifierType;
use Jogo\Domain\Value\StatModifier;
use Jogo\Domain\Weapon\ProjectilePool;
use Jogo\Tests\Support\CombatHarness;
use Jogo\Tests\Support\ManualClock;
use Jogo\Tests\Support\ProgressionFixtures;
use Jogo\Tests\Support\ProjectileFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProjectilePoolLoopTest extends TestCase
{
    /** @return iterable<string, array{list<float>}> */
    public static function schedules(): iterable
    {
        foreach ([30, 60, 144, 240] as $fps) {
            yield "$fps FPS" => [[1.0 / $fps]];
        }
        yield 'irregular' => [[1.0 / 120.0, 1.0 / 40.0, 1.0 / 90.0, 1.0 / 30.0]];
    }

    /** @param list<float> $frames */
    #[DataProvider('schedules')]
    public function test_refusals_reuse_upgrades_and_all_events_are_deterministic_with_a_small_pool(array $frames): void
    {
        $expected = self::simulate([1.0 / 60.0]);
        $actual = self::simulate($frames);
        self::assertSame($expected, $actual);
        self::assertGreaterThan(0, $actual['pool']['refusedBatches']);
        self::assertGreaterThan(2, $actual['pool']['acquisitions']);
        self::assertSame(2, $actual['pool']['peakActive']);
    }

    public function test_pause_keeps_occupancy_position_and_cooldown_unchanged(): void
    {
        $h = new CombatHarness(pool: new ProjectilePool(1));
        $h->world->add('target', 100.0);
        $h->equip('bow');
        $clock = new ManualClock;
        $step = 0;
        $loop = new GameLoop($clock, static function (float $dt) use ($h, &$step): void {
            $h->step($dt, ++$step * GameLoop::STEP_SECONDS);
        }, static function (float $alpha): void {});
        $clock->advance(GameLoop::STEP_SECONDS);
        $loop->tick();
        $p = $h->combat->projectiles()[0];
        $pool = $h->combat->poolSnapshot();
        $position = $p->position();
        $remaining = $h->inventory->at(0)?->remainingSeconds();
        $loop->pause();
        $clock->advance(100.0);
        self::assertSame(0, $loop->tick());
        self::assertEquals($pool, $h->combat->poolSnapshot());
        self::assertSame($position, $p->position());
        self::assertSame($remaining, $h->inventory->at(0)?->remainingSeconds());
        $loop->resume();
        $clock->advance(GameLoop::STEP_SECONDS);
        self::assertSame(1, $loop->tick());
        self::assertSame(7.0, $p->position()->x());
    }

    /**
     * @param  list<float>  $frames
     * @return array{states: list<array<mixed>>, hits: list<array<mixed>>, pool: array<string, int>}
     */
    private static function simulate(array $frames): array
    {
        $h = new CombatHarness(pool: new ProjectilePool(2));
        $h->world->add('target', 100.0, health: 1000.0);
        $h->inventory->add(ProjectileFixtures::bow('first', 2), $h->combat);
        $h->inventory->add(ProjectileFixtures::bow('second', 1), $h->combat);
        $service = ProgressionFixtures::service($h);
        $clock = new ManualClock;
        $step = 0;
        $states = [];
        $loop = new GameLoop($clock, static function (float $dt) use ($h, $service, &$step, &$states): void {
            $step++;
            if ($step === 20) {
                $service->apply(0, [new StatModifier('projeteis', ModifierType::FLAT, 1.0, 'count')]);
            }
            if ($step === 80) {
                $service->remove(0, 'count');
            }
            $accepted = $h->step($dt, $step * GameLoop::STEP_SECONDS);
            $states[] = [$accepted, array_map(static fn ($w): float => $w->remainingSeconds(), $h->inventory->weapons()),
                array_map(static fn ($p): array => [$p->attack->id, $p->attack->definition->id,
                    $p->position()->x(), $p->position()->y(), $p->traveled()], $h->combat->projectiles()),
                get_object_vars($h->combat->poolSnapshot()), get_object_vars($h->combat->collisionSnapshot())];
        }, static function (float $alpha): void {});
        $total = 0.0;
        $frame = 0;
        while ($total < 5.0) {
            $dt = min($frames[$frame++ % count($frames)], 5.0 - $total);
            $total += $dt;
            $clock->advance($dt);
            $loop->tick();
        }
        self::assertCount(300, $states);
        $pool = $h->combat->poolSnapshot();
        $h->combat->clear();
        self::assertSame(2, $h->combat->poolSnapshot()->available);

        return ['states' => $states, 'hits' => array_map(static fn ($hit): array => [$hit->enemyId, $hit->weaponId,
            $hit->appliedDamage, $hit->remainingHealth, $hit->elapsedSeconds], $h->hits),
            'pool' => ['capacity' => $pool->capacity, 'active' => $pool->active, 'available' => $pool->available,
                'peakActive' => $pool->peakActive, 'acquisitions' => $pool->acquisitions,
                'releases' => $pool->releases, 'refusedBatches' => $pool->refusedBatches]];
    }
}
