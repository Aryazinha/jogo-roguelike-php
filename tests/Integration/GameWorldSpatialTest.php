<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Core\EventBus;
use Jogo\Domain\Ai\EntityView;
use Jogo\Domain\Enemy\EnemyCatalog;
use Jogo\Domain\Enemy\EnemyFactory;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\System\DamageSystem;
use Jogo\Domain\System\MovementSystem;
use Jogo\Domain\Value\Damage;
use Jogo\Domain\Value\Facing;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\InputState;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\MapLayout;
use Jogo\Tests\Support\EnemyFixtures;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

/** Mundo real da C sendo consultado, movido e atingido pelos sistemas da A. */
final class GameWorldSpatialTest extends TestCase
{
    public function test_view_sees_real_enemies_and_follows_movement_after_refresh(): void
    {
        [$world, $factory] = self::setUpWorld();
        $near = $factory->create('zombie', new Vector2(1050.0, 1000.0));
        $far = $factory->create('spider', new Vector2(1400.0, 1000.0));
        $world->addEnemy($near);
        $world->addEnemy($far);
        $view = $world->createView();
        $view->refresh();

        self::assertSame(['enemy-1', 'player-1'], self::ids($view->neighbors($world->player()->position(), 100.0)));
        self::assertTrue($view->playerPosition()->equals(new Vector2(1000.0, 1000.0)));

        // A aranha anda 300 px para a esquerda em 1 s (300 px/s) e entra no raio.
        $movement = new MovementSystem($world->layout()->bounds());
        $movement->update($far, new Facing(new Vector2(1.0, 0.0)), new InputState(new Vector2(-1.0, 0.0)), 300.0, 1.0, true);
        $view->refresh();

        self::assertSame(['enemy-1', 'enemy-2', 'player-1'], self::ids($view->neighbors($world->player()->position(), 100.0)));
    }

    public function test_damage_through_the_resolver_kills_and_flush_cleans_up(): void
    {
        [$world, $factory] = self::setUpWorld();
        $world->addEnemy($factory->create('zombie', new Vector2(1020.0, 1000.0)));
        $bus = new EventBus;
        $killed = [];
        $bus->subscribe(EnemyKilled::class, static function (EnemyKilled $event) use (&$killed): void {
            $killed[] = $event->enemyId;
        });
        $damage = new DamageSystem($bus);
        $view = $world->createView();

        $target = $world->resolve('enemy-1');
        self::assertNotNull($target);
        $damage->damageEnemy($target->entity, new Damage(50.0), $target->enemyTypeId, $target->isBoss, 1.0);
        $view->refresh();

        self::assertSame(['enemy-1'], $killed);
        // Morto já some da consulta mesmo antes do flush, porque a A o desativa.
        self::assertSame(['player-1'], self::ids($view->neighbors($world->player()->position(), 100.0)));
        self::assertCount(1, $world->flush());
        self::assertSame(0, $world->aliveEnemyCount());
    }

    public function test_enemy_cannot_walk_out_of_the_map(): void
    {
        [$world, $factory] = self::setUpWorld();
        $zombie = $factory->create('zombie', new Vector2(1990.0, 1000.0));
        $world->addEnemy($zombie);

        (new MovementSystem($world->layout()->bounds()))
            ->update($zombie, new Facing(new Vector2(1.0, 0.0)), new InputState(new Vector2(1.0, 0.0)), 55.0, 1.0, true);

        self::assertSame(1968.0, $zombie->position()->x());
    }

    /** @return array{GameWorld, EnemyFactory} */
    private static function setUpWorld(): array
    {
        $player = new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(20.0));
        $world = new GameWorld($player, new MapLayout(2000.0, 2000.0, 32.0, 500.0, 700.0));

        return [$world, new EnemyFactory(new EnemyCatalog(EnemyFixtures::fullSet()))];
    }

    /**
     * @param  list<EntityView>  $views
     * @return list<string>
     */
    private static function ids(array $views): array
    {
        return array_map(static fn (EntityView $view) => $view->id, $views);
    }
}
