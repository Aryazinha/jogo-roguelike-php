<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Core\GameLoop;
use Jogo\Domain\Spatial\SpatialHash;
use Jogo\Domain\System\CollisionSystem;
use Jogo\Domain\System\MovementSystem;
use Jogo\Domain\Value\Facing;
use Jogo\Domain\Value\InputState;
use Jogo\Domain\Value\MapBounds;
use Jogo\Domain\Value\Vector2;
use Jogo\Tests\Support\FakeCollidable;
use Jogo\Tests\Support\ManualClock;
use PHPUnit\Framework\TestCase;

final class MovementCollisionTest extends TestCase
{
    public function test_fixed_steps_move_then_rebuild_then_confirm_geometry_without_gameplay_consequences(): void
    {
        $clock = new ManualClock;
        $moving = new FakeCollidable('moving', new Vector2(62.0, 32.0), 2.0);
        $stationary = new FakeCollidable('stationary', new Vector2(70.0, 32.0), 2.0);
        $facing = new Facing(new Vector2(1.0, 0.0));
        $movement = new MovementSystem(new MapBounds(-100.0, -100.0, 100.0, 100.0));
        $grid = new SpatialHash;
        $collision = new CollisionSystem;
        $trace = [];
        $loop = new GameLoop(
            $clock,
            static function (float $dt) use ($moving, $stationary, $facing, $movement, $grid, $collision, &$trace): void {
                self::assertSame(GameLoop::STEP_SECONDS, $dt);
                $movement->update($moving, $facing, new InputState(new Vector2(1.0, 0.0)), 120.0, $dt, $moving->isActive());
                $grid->rebuild([$moving, $stationary]);
                $matches = $grid->queryRadius($moving->position(), $moving->collisionRadius(), $moving->id());
                $trace[] = [$matches->entities, $collision->intersects($moving, $stationary)];
            },
            static function (float $alpha): void {},
        );

        $clock->advance(GameLoop::STEP_SECONDS);
        self::assertSame(1, $loop->tick());
        self::assertSame([[], false], $trace[0]);
        $clock->advance(GameLoop::STEP_SECONDS);
        self::assertSame(1, $loop->tick());
        self::assertSame([[$stationary], true], $trace[1]);
        self::assertTrue($moving->position()->equals(new Vector2(66.0, 32.0)));
        self::assertTrue($moving->isActive());
        self::assertTrue($stationary->isActive());
        $stationary->deactivate();
        $clock->advance(GameLoop::STEP_SECONDS);
        self::assertSame(1, $loop->tick());
        self::assertSame([[], false], $trace[2]);
    }
}
