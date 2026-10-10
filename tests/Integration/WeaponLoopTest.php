<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Core\Config\WeaponConfigLoader;
use Jogo\Core\GameLoop;
use Jogo\Domain\Weapon\CombatInventory;
use Jogo\Domain\Weapon\WeaponDefinition;
use Jogo\Tests\Support\ManualClock;
use Jogo\Tests\Support\WeaponFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WeaponLoopTest extends TestCase
{
    /** @return iterable<string, array{int}> */
    public static function frameRates(): iterable
    {
        foreach ([30, 60, 120, 144, 240] as $fps) {
            yield "$fps FPS" => [$fps];
        }
    }

    #[DataProvider('frameRates')]
    public function test_two_automatic_weapons_have_the_same_fixed_step_cadence_at_different_frame_rates(int $fps): void
    {
        $inventory = new CombatInventory;
        $step = 0;
        $trace = [];
        $execute = static function (WeaponDefinition $definition) use (&$step, &$trace): bool {
            $trace[] = [$definition->id, $step];

            return true;
        };
        $inventory->add(WeaponFixtures::definition('slow', 2.0), $execute);
        $inventory->add(WeaponFixtures::definition('fast', 0.5), $execute);
        $clock = new ManualClock;
        $loop = new GameLoop($clock,
            static function (float $dt) use ($inventory, &$step): void {
                $step++;
                $inventory->update($dt);
            },
            static function (float $alpha): void {},
        );
        for ($frame = 0; $frame < (int) ($fps * 2.5); $frame++) {
            $clock->advance(1.0 / $fps);
            $loop->tick();
        }
        self::assertSame(150, $step);
        self::assertSame([['slow', 1], ['fast', 1], ['fast', 31], ['fast', 61], ['fast', 91], ['slow', 121], ['fast', 121]], $trace);
    }

    public function test_pause_freezes_both_cooldowns_and_resume_excludes_paused_time(): void
    {
        $inventory = new CombatInventory;
        $calls = [];
        $execute = static function (WeaponDefinition $definition) use (&$calls): bool {
            $calls[] = $definition->id;

            return true;
        };
        $inventory->add(WeaponFixtures::definition('slow', 2.0), $execute);
        $inventory->add(WeaponFixtures::definition('fast', 0.5), $execute);
        $clock = new ManualClock;
        $renders = 0;
        $loop = new GameLoop($clock,
            static function (float $dt) use ($inventory): void {
                $inventory->update($dt);
            },
            static function (float $alpha) use (&$renders): void {
                $renders++;
            },
        );
        $clock->advance(GameLoop::STEP_SECONDS);
        self::assertSame(1, $loop->tick());
        $slow = $inventory->at(0);
        $fast = $inventory->at(1);
        self::assertNotNull($slow);
        self::assertNotNull($fast);
        $clock->advance(GameLoop::STEP_SECONDS);
        self::assertSame(1, $loop->tick());
        $slowRemaining = $slow->remainingSeconds();
        $fastRemaining = $fast->remainingSeconds();
        self::assertSame(['slow', 'fast'], $calls);
        $loop->pause();
        $clock->advance(120.0);
        self::assertSame(0, $loop->tick());
        self::assertSame($slowRemaining, $slow->remainingSeconds());
        self::assertSame($fastRemaining, $fast->remainingSeconds());
        self::assertSame(['slow', 'fast'], $calls);
        self::assertSame(3, $renders);
        $loop->resume();
        self::assertSame(0, $loop->tick());
        $clock->advance(GameLoop::STEP_SECONDS);
        self::assertSame(1, $loop->tick());
        self::assertEqualsWithDelta($slowRemaining - GameLoop::STEP_SECONDS, $slow->remainingSeconds(), 1.0E-12);
        self::assertEqualsWithDelta($fastRemaining - GameLoop::STEP_SECONDS, $fast->remainingSeconds(), 1.0E-12);
        self::assertSame(['slow', 'fast'], $calls);
    }

    public function test_real_definitions_can_be_equipped_without_executing_concrete_attacks(): void
    {
        $catalog = (new WeaponConfigLoader)->load(dirname(__DIR__, 2).'/config/weapons.json');
        $inventory = new CombatInventory;
        $trace = [];
        $execute = static function (WeaponDefinition $definition) use (&$trace): bool {
            $trace[] = $definition->id;

            return true;
        };
        $inventory->add($catalog->get('stone_sword'), $execute);
        $inventory->add($catalog->get('bow'), $execute);
        self::assertSame(2, $inventory->update(GameLoop::STEP_SECONDS));
        self::assertSame(['stone_sword', 'bow'], $trace);
        $stone = $inventory->at(0);
        $bow = $inventory->at(1);
        self::assertNotNull($stone);
        self::assertNotNull($bow);
        self::assertSame(2.0, $stone->remainingSeconds());
        self::assertSame(1.8, $bow->remainingSeconds());
        self::assertSame(1, $inventory->update(1.8));
        self::assertSame(['stone_sword', 'bow', 'bow'], $trace);
        self::assertEqualsWithDelta(0.2, $stone->remainingSeconds(), 1.0E-12);
        self::assertSame(1.8, $bow->remainingSeconds());
    }
}
