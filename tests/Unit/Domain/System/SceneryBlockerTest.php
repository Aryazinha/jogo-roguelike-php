<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\System;

use Jogo\Domain\Enemy\Enemy;
use Jogo\Domain\Scenery\Breakable;
use Jogo\Domain\Scenery\BreakableType;
use Jogo\Domain\Scenery\DropChance;
use Jogo\Domain\System\SceneryBlocker;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\MapLayout;
use Jogo\Tests\Support\EnemyFixtures;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

final class SceneryBlockerTest extends TestCase
{
    public function test_player_inside_a_crate_is_pushed_to_its_edge(): void
    {
        $world = self::world();
        $world->addBreakable(new Breakable('crate-1', self::type(true), new Vector2(500.0, 500.0)));
        $world->player()->moveTo(new Vector2(480.0, 500.0));

        (new SceneryBlocker)->update($world);

        // Raio do jogador 16 + caixote 18: fica a 34 px do centro, do lado de onde veio.
        self::assertTrue($world->player()->position()->equals(new Vector2(466.0, 500.0)));
    }

    public function test_enemies_are_blocked_too(): void
    {
        $world = self::world();
        $world->addBreakable(new Breakable('crate-1', self::type(true), new Vector2(500.0, 500.0)));
        $zombie = new Enemy('enemy-1', EnemyFixtures::common('zombie'), new Vector2(500.0, 520.0));
        $world->addEnemy($zombie);

        (new SceneryBlocker)->update($world);

        self::assertEqualsWithDelta(32.0, $zombie->position()->distanceTo(new Vector2(500.0, 500.0)), 1e-9);
    }

    public function test_grass_broken_crates_and_far_movers_are_left_alone(): void
    {
        $world = self::world();
        $world->addBreakable(new Breakable('grass-1', self::type(false), new Vector2(500.0, 500.0)));
        $broken = new Breakable('crate-1', self::type(true), new Vector2(700.0, 500.0));
        $world->addBreakable($broken);
        $broken->health()->damage(10.0);
        $zombie = new Enemy('enemy-1', EnemyFixtures::common('zombie'), new Vector2(705.0, 500.0));
        $world->addEnemy($zombie);
        $world->player()->moveTo(new Vector2(505.0, 500.0));

        (new SceneryBlocker)->update($world);

        self::assertTrue($world->player()->position()->equals(new Vector2(505.0, 500.0)));
        self::assertTrue($zombie->position()->equals(new Vector2(705.0, 500.0)));
    }

    public function test_exact_center_pushes_to_the_right(): void
    {
        $world = self::world();
        $world->addBreakable(new Breakable('crate-1', self::type(true), new Vector2(500.0, 500.0)));
        $world->player()->moveTo(new Vector2(500.0, 500.0));

        (new SceneryBlocker)->update($world);

        self::assertTrue($world->player()->position()->equals(new Vector2(534.0, 500.0)));
    }

    private static function world(): GameWorld
    {
        return new GameWorld(new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(20.0)),
            new MapLayout(1000.0, 1000.0, 10.0, 100.0, 200.0));
    }

    private static function type(bool $blocks): BreakableType
    {
        return new BreakableType($blocks ? 'crate' : 'grass', 'x', 3.0, 18.0, $blocks, [new DropChance('nenhum', 1.0)]);
    }
}
