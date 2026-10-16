<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\World;

use InvalidArgumentException;
use Jogo\Domain\Ai\WorldEntity;
use Jogo\Domain\Enemy\Enemy;
use Jogo\Domain\Enemy\EnemyProjectile;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\MapLayout;
use Jogo\Tests\Support\EnemyFixtures;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

final class GameWorldTest extends TestCase
{
    public function test_player_is_placed_at_the_start_position(): void
    {
        $world = self::world();

        self::assertTrue($world->player()->position()->equals(new Vector2(500.0, 500.0)));
    }

    public function test_added_enemies_are_listed_and_resolved_as_targets(): void
    {
        $world = self::world();
        $world->addEnemy(self::zombie('enemy-1'));
        $world->addEnemy(self::zombie('enemy-2'));

        self::assertSame(['enemy-1', 'enemy-2'], array_map(static fn (Enemy $e) => $e->id(), $world->enemies()));
        self::assertSame(2, $world->aliveEnemyCount());
        self::assertSame('zombie', $world->resolve('enemy-2')?->enemyTypeId);
        self::assertNull($world->resolve('enemy-9'));
        self::assertNull($world->resolve('player-1'));
    }

    public function test_entities_include_the_player_and_enemies_with_their_kind(): void
    {
        $world = self::world();
        $world->addEnemy(self::zombie('enemy-1'));

        $records = iterator_to_array($world->entities(), false);

        self::assertSame([['player-1', 'player', true], ['enemy-1', 'enemy', true]], array_map(
            static fn (WorldEntity $r) => [$r->entity->id(), $r->kind, $r->isAlive], $records));
    }

    public function test_snapshots_list_active_entities_with_kind_and_life(): void
    {
        $world = self::world();
        $alive = self::zombie('enemy-1');
        $dead = self::zombie('enemy-2');
        $gone = self::zombie('enemy-3');
        foreach ([$alive, $dead, $gone] as $enemy) {
            $world->addEnemy($enemy);
        }
        $dead->health()->damage(100.0);
        $gone->deactivate();

        $views = $world->snapshots();

        self::assertSame([['player-1', 'player', true], ['enemy-1', 'enemy', true], ['enemy-2', 'enemy', false]],
            array_map(static fn ($v) => [$v->id, $v->kind, $v->isAlive], $views));
        self::assertTrue($views[1]->position->equals(new Vector2(100.0, 100.0)));
    }

    public function test_projectiles_are_listed_seen_and_cleaned_up(): void
    {
        $world = self::world();
        $arrow = new EnemyProjectile('arrow-1', new Vector2(200.0, 200.0), 4.0, 'enemy-1', 3.0, new Vector2(1.0, 0.0), 100.0);
        $spent = new EnemyProjectile('arrow-2', new Vector2(300.0, 200.0), 4.0, 'enemy-1', 3.0, new Vector2(1.0, 0.0), 100.0);
        $world->addProjectile($arrow);
        $world->addProjectile($spent);
        $spent->deactivate();

        $kinds = array_map(static fn ($v) => [$v->id, $v->kind, $v->isAlive], $world->snapshots());
        self::assertSame([['player-1', 'player', true], ['arrow-1', 'projectile', null]], $kinds);
        self::assertContains('projectile', array_map(static fn (WorldEntity $r) => $r->kind, iterator_to_array($world->entities(), false)));
        self::assertSame(0, $world->aliveEnemyCount(), 'flecha não conta como inimigo');

        $world->flush();
        self::assertSame([$arrow], $world->projectiles());
    }

    public function test_ids_are_unique_across_enemies_projectiles_and_queue(): void
    {
        $world = self::world();
        $world->addProjectile(new EnemyProjectile('x-1', Vector2::zero(), 4.0, 'enemy-1', 3.0, new Vector2(1.0, 0.0), 10.0));
        $world->queueEnemy(self::zombie('x-2'));

        foreach (['x-1', 'x-2', 'player-1'] as $id) {
            try {
                $world->queueEnemy(self::zombie($id));
                self::fail("{$id} repetido deveria falhar.");
            } catch (InvalidArgumentException $error) {
                self::assertStringContainsString('ID já usado', $error->getMessage());
            }
        }
    }

    public function test_repeated_ids_are_rejected(): void
    {
        $world = self::world();
        $world->addEnemy(self::zombie('enemy-1'));

        foreach (['enemy-1', 'player-1'] as $id) {
            try {
                $world->addEnemy(self::zombie($id));
                self::fail("{$id} repetido deveria falhar.");
            } catch (InvalidArgumentException $error) {
                self::assertStringContainsString('ID já usado', $error->getMessage());
            }
        }
    }

    public function test_dead_enemy_cannot_be_added(): void
    {
        $zombie = self::zombie('enemy-1');
        $zombie->health()->damage(100.0);

        $this->expectExceptionMessage('já chegou morto');

        self::world()->addEnemy($zombie);
    }

    public function test_flush_removes_dead_and_inactive_enemies(): void
    {
        $world = self::world();
        $dead = self::zombie('enemy-1');
        $gone = self::zombie('enemy-2');
        $alive = self::zombie('enemy-3');
        foreach ([$dead, $gone, $alive] as $enemy) {
            $world->addEnemy($enemy);
        }
        $dead->health()->damage(100.0);
        $gone->deactivate();

        self::assertSame(1, $world->aliveEnemyCount());
        $removed = $world->flush();

        self::assertSame([$dead, $gone], $removed);
        self::assertFalse($dead->isActive());
        self::assertSame([$alive], $world->enemies());
        self::assertSame([], $world->flush());
    }

    public function test_queued_enemy_only_appears_after_flush(): void
    {
        $world = self::world();
        $child = self::zombie('enemy-1');

        $world->queueEnemy($child);

        self::assertFalse($world->has('enemy-1'));
        self::assertSame(1, $world->aliveEnemyCount(), 'o teto já precisa contar quem vai entrar');
        $world->flush();
        self::assertTrue($world->has('enemy-1'));
    }

    public function test_queue_rejects_repeated_ids(): void
    {
        $world = self::world();
        $world->queueEnemy(self::zombie('enemy-1'));

        $this->expectExceptionMessage('ID já usado');

        $world->queueEnemy(self::zombie('enemy-1'));
    }

    public function test_enemy_list_is_a_copy(): void
    {
        $world = self::world();
        $world->addEnemy(self::zombie('enemy-1'));

        $before = $world->enemies();
        $world->addEnemy(self::zombie('enemy-2'));

        self::assertCount(1, $before);
    }

    private static function world(): GameWorld
    {
        $player = new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(20.0));

        return new GameWorld($player, new MapLayout(1000.0, 1000.0, 20.0, 100.0, 200.0));
    }

    private static function zombie(string $id): Enemy
    {
        return new Enemy($id, EnemyFixtures::common('zombie'), new Vector2(100.0, 100.0));
    }
}
