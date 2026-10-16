<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\System;

use Jogo\Domain\Enemy\Enemy;
use Jogo\Domain\System\RangedAttackSystem;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\MapLayout;
use Jogo\Tests\Support\EnemyFixtures;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

/** Skeleton das fixtures: faixa 160-240, cooldown 2,2 s, flecha 260 px/s e raio 4. */
final class RangedAttackSystemTest extends TestCase
{
    public function test_skeleton_in_range_fires_an_arrow_at_the_player(): void
    {
        $world = self::world();
        $world->addEnemy(self::skeleton('enemy-1', 1200.0));

        self::assertSame(1, (new RangedAttackSystem)->update($world, 1 / 60));

        $arrow = $world->projectiles()[0];
        self::assertSame('arrow-1', $arrow->id());
        self::assertSame('enemy-1', $arrow->ownerId());
        self::assertSame(3.0, $arrow->damage());
        // Sai da borda do Skeleton (raio 14 + flecha 4) em direção ao jogador à esquerda.
        self::assertTrue($arrow->position()->equals(new Vector2(1182.0, 1000.0)));
        self::assertTrue($arrow->velocity()->equals(new Vector2(-260.0, 0.0)));
    }

    public function test_out_of_range_it_does_not_fire(): void
    {
        $world = self::world();
        $world->addEnemy(self::skeleton('enemy-1', 1240.5));

        self::assertSame(0, (new RangedAttackSystem)->update($world, 1 / 60));
        self::assertSame([], $world->projectiles());
    }

    public function test_cooldown_spaces_the_shots(): void
    {
        $world = self::world();
        $world->addEnemy(self::skeleton('enemy-1', 1200.0));
        $system = new RangedAttackSystem;
        $times = [];

        for ($step = 1; $step <= 60 * 5; $step++) {
            if ($system->update($world, 1 / 60) > 0) {
                $times[] = $step;
            }
        }

        // Um tiro logo, outro 2,2 s depois (132 passos) e outro 2,2 s depois.
        self::assertSame([1, 133, 265], $times);
    }

    public function test_only_living_ranged_enemies_fire_at_a_living_player(): void
    {
        $world = self::world();
        $dead = self::skeleton('enemy-1', 1200.0);
        $world->addEnemy($dead);
        $world->addEnemy(new Enemy('enemy-2', EnemyFixtures::common('zombie'), new Vector2(1100.0, 1000.0)));
        $dead->health()->damage(100.0);

        self::assertSame(0, (new RangedAttackSystem)->update($world, 1 / 60));

        $world = self::world();
        $world->addEnemy(self::skeleton('enemy-1', 1200.0));
        $world->player()->deactivate();
        self::assertSame(0, (new RangedAttackSystem)->update($world, 1 / 60));
    }

    public function test_two_skeletons_have_independent_cooldowns_and_unique_arrows(): void
    {
        $world = self::world();
        $world->addEnemy(self::skeleton('enemy-1', 1200.0));
        $system = new RangedAttackSystem;
        $system->update($world, 1 / 60);
        $world->addEnemy(self::skeleton('enemy-2', 800.0));

        self::assertSame(1, $system->update($world, 1 / 60), 'só o novo está pronto');
        self::assertSame(['arrow-1', 'arrow-2'], array_map(static fn ($p) => $p->id(), $world->projectiles()));
        self::assertSame('enemy-2', $world->projectiles()[1]->ownerId());
    }

    private static function world(): GameWorld
    {
        $player = new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(20.0));

        return new GameWorld($player, new MapLayout(2000.0, 2000.0, 32.0, 500.0, 700.0));
    }

    private static function skeleton(string $id, float $x): Enemy
    {
        return new Enemy($id, EnemyFixtures::skeleton(), new Vector2($x, 1000.0));
    }
}
