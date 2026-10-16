<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Core\Config\CharacterConfigLoader;
use Jogo\Core\Config\WeaponConfigLoader;
use Jogo\Core\GameLoop;
use Jogo\Domain\Character\Player;
use Jogo\Domain\Character\PlayerFactory;
use Jogo\Domain\Value\Vector2;
use Jogo\Tests\Support\ManualClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlayerRegenerationLoopTest extends TestCase
{
    /** @return iterable<string, array{int}> */
    public static function frameRates(): iterable
    {
        yield '30 FPS' => [30];
        yield '60 FPS' => [60];
        yield '144 FPS' => [144];
        yield '240 FPS' => [240];
    }

    #[DataProvider('frameRates')]
    public function test_steve_regenerates_on_the_logical_clock_regardless_of_render_rate(int $fps): void
    {
        $clock = new ManualClock;
        $player = self::player('steve');
        $player->health()->damage(5.0);
        $loop = new GameLoop($clock, static function (float $delta) use ($player): void {
            $player->regenerate($delta);
        }, static function (): void {});

        self::runUntil($loop, $clock, $fps, 29.9);
        self::assertSame(15.0, $player->health()->current());

        self::runUntil($loop, $clock, $fps, 30.1);
        self::assertSame(16.0, $player->health()->current());
    }

    public function test_pause_freezes_regeneration(): void
    {
        $clock = new ManualClock;
        $player = self::player('alex');
        $player->health()->damage(5.0);
        $loop = new GameLoop($clock, static function (float $delta) use ($player): void {
            $player->regenerate($delta);
        }, static function (): void {});

        self::runUntil($loop, $clock, 60, 10.0);
        $loop->pause();
        self::runUntil($loop, $clock, 60, 110.0);
        self::assertSame(11.0, $player->health()->current());

        $loop->resume();
        self::runUntil($loop, $clock, 60, 114.9);
        self::assertSame(11.0, $player->health()->current());
        self::runUntil($loop, $clock, 60, 115.1);
        self::assertSame(12.0, $player->health()->current());
    }

    private static function runUntil(GameLoop $loop, ManualClock $clock, int $fps, float $seconds): void
    {
        while ($clock->now() < $seconds) {
            $clock->advance(1.0 / $fps);
            $loop->tick();
        }
    }

    private static function player(string $characterId): Player
    {
        $config = dirname(__DIR__, 2).'/config';
        $weapons = (new WeaponConfigLoader)->load($config.'/weapons.json');
        $characters = (new CharacterConfigLoader($weapons))->load($config.'/characters.json');

        return (new PlayerFactory($characters, $weapons))->create($characterId, 'player-1', Vector2::zero(), 6.0,
            static fn (): bool => false);
    }
}
