<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Enemy;

use Jogo\Core\EventBus;
use Jogo\Domain\Enemy\Enemy;
use Jogo\Domain\Enemy\EnemyCatalog;
use Jogo\Domain\Enemy\EnemyFactory;
use Jogo\Domain\Enemy\SplitOnDeath;
use Jogo\Domain\System\DamageSystem;
use Jogo\Domain\Value\Damage;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\MapLayout;
use Jogo\Tests\Support\EnemyFixtures;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

final class SplitOnDeathTest extends TestCase
{
    public function test_slime_leaves_two_small_slimes_only_after_flush(): void
    {
        [$world, $factory, $damage] = self::setUpWorld();
        $slime = $factory->create('slime', new Vector2(500.0, 500.0));
        $world->addEnemy($slime);

        self::kill($damage, $slime);

        self::assertSame(['enemy-1'], self::ids($world->enemies()), 'nada entra no meio do combate');
        $world->flush();

        $children = $world->enemies();
        self::assertSame(['enemy-2', 'enemy-3'], self::ids($children));
        foreach ($children as $child) {
            self::assertSame('slime_pequeno', $child->typeId());
            self::assertSame(6.0, $child->health()->current());
        }
    }

    public function test_children_are_spread_around_the_death_point(): void
    {
        [$world, $factory, $damage] = self::setUpWorld();
        $slime = $factory->create('slime', new Vector2(500.0, 500.0));
        $world->addEnemy($slime);

        self::kill($damage, $slime);
        $world->flush();

        // Raio do Slime 20, metade para cada lado: (510, 500) e (490, 500).
        [$first, $second] = $world->enemies();
        self::assertTrue($first->position()->equals(new Vector2(510.0, 500.0)));
        self::assertTrue($second->position()->equals(new Vector2(490.0, 500.0), 1e-9));
    }

    public function test_small_slime_does_not_split_again(): void
    {
        [$world, $factory, $damage] = self::setUpWorld();
        $world->addEnemy($factory->create('slime', new Vector2(500.0, 500.0)));
        self::kill($damage, $world->enemies()[0]);
        $world->flush();

        foreach ($world->enemies() as $child) {
            self::kill($damage, $child);
        }
        $world->flush();

        self::assertSame([], $world->enemies());
    }

    public function test_full_world_only_gets_the_children_that_fit(): void
    {
        [$world, $factory, $damage] = self::setUpWorld();
        $world->setEnemyCap(2);
        $slime = $factory->create('slime', new Vector2(500.0, 500.0));
        $world->addEnemy($slime);
        $world->addEnemy($factory->create('zombie', new Vector2(800.0, 800.0)));

        self::kill($damage, $slime);
        $world->flush();

        // Teto 2: o Zombie ocupa uma vaga e o Slime morto libera a dele, então nasce um filho só.
        self::assertSame(['zombie', 'slime_pequeno'], array_map(static fn (Enemy $e) => $e->typeId(), $world->enemies()));
    }

    public function test_common_enemy_leaves_nothing(): void
    {
        [$world, $factory, $damage] = self::setUpWorld();
        $world->addEnemy($factory->create('zombie', new Vector2(500.0, 500.0)));

        self::kill($damage, $world->enemies()[0]);
        $world->flush();

        self::assertSame([], $world->enemies());
    }

    public function test_children_near_the_border_stay_inside_the_map(): void
    {
        [$world, $factory, $damage] = self::setUpWorld();
        $slime = $factory->create('slime', new Vector2(20.0, 500.0));
        $world->addEnemy($slime);

        self::kill($damage, $slime);
        $world->flush();

        foreach ($world->enemies() as $child) {
            self::assertTrue($world->layout()->contains($child->position()));
        }
    }

    public function test_extra_hits_on_a_dead_slime_do_not_create_more_children(): void
    {
        [$world, $factory, $damage] = self::setUpWorld();
        $slime = $factory->create('slime', new Vector2(500.0, 500.0));
        $world->addEnemy($slime);

        self::kill($damage, $slime);
        self::kill($damage, $slime);
        $world->flush();

        self::assertCount(2, $world->enemies());
    }

    /** @return array{GameWorld, EnemyFactory, DamageSystem} */
    private static function setUpWorld(): array
    {
        $player = new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(20.0));
        $world = new GameWorld($player, new MapLayout(1000.0, 1000.0, 10.0, 100.0, 200.0));
        $factory = new EnemyFactory(new EnemyCatalog(EnemyFixtures::fullSet()));
        $bus = new EventBus;
        (new SplitOnDeath($world, $factory))->subscribe($bus);

        return [$world, $factory, new DamageSystem($bus)];
    }

    private static function kill(DamageSystem $damage, Enemy $enemy): void
    {
        $target = $enemy->asTarget();
        $damage->damageEnemy($enemy, new Damage(1000.0), $target->enemyTypeId, $target->isBoss, 1.0);
    }

    /**
     * @param  list<Enemy>  $enemies
     * @return list<string>
     */
    private static function ids(array $enemies): array
    {
        return array_map(static fn (Enemy $e) => $e->id(), $enemies);
    }
}
