<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Application\Progression\ProgressionSession;
use Jogo\Domain\System\MovementSystem;
use Jogo\Domain\Value\Facing;
use Jogo\Domain\Value\InputState;
use Jogo\Domain\Value\MapBounds;
use Jogo\Domain\Value\Vector2;
use Jogo\Tests\Support\CombatHarness;
use PHPUnit\Framework\TestCase;

final class PlayerAttributesTest extends TestCase
{
    public function test_session_gives_the_player_real_movement_speed_and_pickup_radius(): void
    {
        $session = self::session();

        self::assertSame(100.0, $session->player->movementSpeed());
        self::assertSame(40.0, $session->player->pickupRadius());
    }

    public function test_feather_makes_the_movement_system_move_the_player_further(): void
    {
        $session = self::session();
        self::assertEqualsWithDelta(100.0, self::distanceInOneSecond($session), 1.0E-9);

        $session->passives->acquire('feather');

        self::assertEqualsWithDelta(106.0, $session->player->movementSpeed(), 1.0E-9);
        self::assertEqualsWithDelta(106.0, self::distanceInOneSecond($session), 1.0E-9);
    }

    public function test_agility_bonus_speeds_up_movement_only_while_active(): void
    {
        $session = self::session();
        $session->pickups->collectBonus('drop-1', 'agilidade', 0.0);

        self::assertEqualsWithDelta(130.0, self::distanceInOneSecond($session), 1.0E-9);

        $session->bonuses->update(15.0);
        self::assertEqualsWithDelta(100.0, self::distanceInOneSecond($session), 1.0E-9);
    }

    public function test_magnet_enlarges_the_pickup_radius_used_for_collection(): void
    {
        $session = self::session();
        $pickup = new Vector2(45.0, 0.0);
        self::assertFalse($session->canReach($pickup));
        self::assertTrue($session->canReach(new Vector2(40.0, 0.0)));

        $session->passives->acquire('magnet');

        self::assertEqualsWithDelta(46.0, $session->player->pickupRadius(), 1.0E-9);
        self::assertTrue($session->canReach($pickup));
    }

    private static function distanceInOneSecond(ProgressionSession $session): float
    {
        $player = $session->player;
        $player->moveTo(Vector2::zero());
        $movement = new MovementSystem(new MapBounds(-10000.0, -10000.0, 10000.0, 10000.0));
        $facing = new Facing(new Vector2(1.0, 0.0));
        for ($step = 0; $step < 60; $step++) {
            $movement->update($player, $facing, new InputState(new Vector2(1.0, 0.0)), $player->movementSpeed(), 1.0 / 60.0, true);
        }

        return $player->position()->x();
    }

    private static function session(): ProgressionSession
    {
        $harness = new CombatHarness;

        return ProgressionSession::start(dirname(__DIR__, 2).'/config', 'steve', 'player', Vector2::zero(), 6.0,
            100.0, 40.0, 3, $harness->events, $harness->combat, $harness->combat);
    }
}
