<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\System;

use InvalidArgumentException;
use Jogo\Domain\Ai\Behavior\BehaviorStrategy;
use Jogo\Domain\Ai\Behavior\SeekBehavior;
use Jogo\Domain\Ai\Behavior\SteeringSettings;
use Jogo\Domain\Ai\EntityView;
use Jogo\Domain\Ai\WorldView;
use Jogo\Domain\Enemy\Enemy;
use Jogo\Domain\System\EnemySteeringSystem;
use Jogo\Domain\System\MovementSystem;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\MapLayout;
use Jogo\Tests\Support\EnemyFixtures;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

final class EnemySteeringSystemTest extends TestCase
{
    public function test_chaser_walks_toward_the_player_at_its_speed(): void
    {
        $world = self::world();
        // Zombie a 200 px à esquerda do jogador, velocidade 55 px/s.
        $zombie = new Enemy('enemy-1', EnemyFixtures::common('zombie'), new Vector2(800.0, 1000.0));
        $world->addEnemy($zombie);

        self::system()->update($world, 1.0);

        self::assertTrue($zombie->position()->equals(new Vector2(855.0, 1000.0)));
        self::assertTrue($zombie->facing()->direction()->equals(new Vector2(1.0, 0.0)));
    }

    public function test_facing_follows_the_movement(): void
    {
        $world = self::world();
        $zombie = new Enemy('enemy-1', EnemyFixtures::common('zombie'), new Vector2(1000.0, 1300.0));
        $world->addEnemy($zombie);

        self::system()->update($world, 0.5);

        self::assertTrue($zombie->facing()->direction()->equals(new Vector2(0.0, -1.0)));
    }

    public function test_zero_step_moves_nothing(): void
    {
        $world = self::world();
        $zombie = new Enemy('enemy-1', EnemyFixtures::common('zombie'), new Vector2(800.0, 1000.0));
        $world->addEnemy($zombie);

        self::system()->update($world, 0.0);

        self::assertTrue($zombie->position()->equals(new Vector2(800.0, 1000.0)));
    }

    public function test_dead_and_inactive_enemies_stay_still(): void
    {
        $world = self::world();
        $dead = new Enemy('enemy-1', EnemyFixtures::common('zombie'), new Vector2(800.0, 1000.0));
        $inactive = new Enemy('enemy-2', EnemyFixtures::common('zombie'), new Vector2(1200.0, 1000.0));
        $world->addEnemy($dead);
        $world->addEnemy($inactive);
        $dead->health()->damage(100.0);
        $inactive->deactivate();

        self::system()->update($world, 1.0);

        self::assertSame(800.0, $dead->position()->x());
        self::assertSame(1200.0, $inactive->position()->x());
    }

    public function test_order_of_the_list_does_not_change_the_result(): void
    {
        $positions = [];
        foreach ([['enemy-1', 'enemy-2'], ['enemy-2', 'enemy-1']] as $order) {
            $world = self::world();
            $start = ['enemy-1' => new Vector2(990.0, 900.0), 'enemy-2' => new Vector2(1000.0, 900.0)];
            foreach ($order as $id) {
                $world->addEnemy(new Enemy($id, EnemyFixtures::common('zombie'), $start[$id]));
            }
            (new EnemySteeringSystem(new MovementSystem($world->layout()->bounds()),
                ['perseguir' => self::chaseWithSeparation()]))->update($world, 0.5);

            $positions[] = [(string) $world->enemy('enemy-1')?->position()->x(), (string) $world->enemy('enemy-2')?->position()->x()];
        }

        self::assertSame($positions[0], $positions[1]);
    }

    public function test_strategy_for_the_type_wins_over_the_behavior(): void
    {
        $world = self::world();
        $spider = new Enemy('enemy-1', EnemyFixtures::common('spider'), new Vector2(800.0, 1000.0));
        $zombie = new Enemy('enemy-2', EnemyFixtures::common('zombie'), new Vector2(800.0, 900.0));
        $world->addEnemy($spider);
        $world->addEnemy($zombie);
        $stay = new class implements BehaviorStrategy
        {
            public function direction(EntityView $enemy, WorldView $world, float $deltaSeconds): Vector2
            {
                return Vector2::zero();
            }
        };

        (new EnemySteeringSystem(new MovementSystem($world->layout()->bounds()),
            ['perseguir' => new SeekBehavior, 'spider' => $stay]))->update($world, 1.0);

        self::assertTrue($spider->position()->equals(new Vector2(800.0, 1000.0)));
        self::assertFalse($zombie->position()->equals(new Vector2(800.0, 900.0)));
    }

    public function test_boss_is_left_to_the_boss_system(): void
    {
        $world = self::world();
        $boss = new Enemy('enemy-1', EnemyFixtures::boss(), new Vector2(800.0, 1000.0));
        $world->addEnemy($boss);

        self::system()->update($world, 1.0);

        self::assertTrue($boss->position()->equals(new Vector2(800.0, 1000.0)));
    }

    public function test_missing_strategy_is_reported(): void
    {
        $world = self::world();
        $world->addEnemy(new Enemy('enemy-1', EnemyFixtures::skeleton(), new Vector2(800.0, 1000.0)));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nenhuma estratégia registrada para o comportamento manter_distancia.');

        self::system()->update($world, 1.0);
    }

    private static function system(): EnemySteeringSystem
    {
        return new EnemySteeringSystem(new MovementSystem(self::world()->layout()->bounds()), ['perseguir' => new SeekBehavior]);
    }

    private static function chaseWithSeparation(): BehaviorStrategy
    {
        return (new SteeringSettings(40.0, 1.0, 1.4))->chase();
    }

    private static function world(): GameWorld
    {
        $player = new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(20.0));

        return new GameWorld($player, new MapLayout(2000.0, 2000.0, 32.0, 500.0, 700.0));
    }
}
