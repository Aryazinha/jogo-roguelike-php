<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Enemy;

use InvalidArgumentException;
use Jogo\Domain\Enemy\EnemyCatalog;
use Jogo\Domain\Enemy\EnemyFactory;
use Jogo\Domain\Value\Vector2;
use Jogo\Tests\Support\EnemyFixtures;
use PHPUnit\Framework\TestCase;

final class EnemyFactoryTest extends TestCase
{
    public function test_created_enemy_starts_with_the_archetype_values(): void
    {
        $factory = self::factory();

        $zombie = $factory->create('zombie', new Vector2(100.0, 50.0));

        self::assertSame('zombie', $zombie->typeId());
        self::assertTrue($zombie->position()->equals(new Vector2(100.0, 50.0)));
        self::assertSame(14.0, $zombie->collisionRadius());
        self::assertSame(13.0, $zombie->health()->current());
        self::assertSame(13.0, $zombie->health()->maximum());
        self::assertSame(3.0, $zombie->contactDamage());
        self::assertSame(55.0, $zombie->speed());
        self::assertTrue($zombie->isActive());
        self::assertFalse($zombie->isBoss());
    }

    public function test_ids_are_sequential_and_unique(): void
    {
        $factory = self::factory();

        $first = $factory->create('spider', Vector2::zero());
        $second = $factory->create('spider', Vector2::zero());
        $third = $factory->create('zombie', Vector2::zero());

        self::assertSame(['enemy-1', 'enemy-2', 'enemy-3'], [$first->id(), $second->id(), $third->id()]);
        self::assertSame(3, $factory->createdCount());
    }

    public function test_two_factories_repeat_the_same_ids(): void
    {
        $a = self::factory()->create('zombie', Vector2::zero());
        $b = self::factory()->create('zombie', Vector2::zero());

        self::assertSame($a->id(), $b->id());
    }

    public function test_instances_share_the_archetype_but_not_the_health(): void
    {
        $factory = self::factory();
        $first = $factory->create('zombie', Vector2::zero());
        $second = $factory->create('zombie', Vector2::zero());

        $first->health()->damage(5.0);

        self::assertSame($first->archetype(), $second->archetype());
        self::assertSame(8.0, $first->health()->current());
        self::assertSame(13.0, $second->health()->current());
    }

    public function test_target_carries_type_and_boss_flag_for_the_damage_system(): void
    {
        $factory = self::factory();

        $boss = $factory->create('warden', Vector2::zero())->asTarget();
        $spider = $factory->create('spider', Vector2::zero())->asTarget();

        self::assertSame(['warden', true], [$boss->enemyTypeId, $boss->isBoss]);
        self::assertSame(['spider', false], [$spider->enemyTypeId, $spider->isBoss]);
    }

    public function test_unknown_type_does_not_consume_an_id(): void
    {
        $factory = self::factory();

        try {
            $factory->create('creeper', Vector2::zero());
            self::fail('Tipo inexistente deveria falhar.');
        } catch (InvalidArgumentException) {
        }

        self::assertSame(0, $factory->createdCount());
        self::assertSame('enemy-1', $factory->create('zombie', Vector2::zero())->id());
    }

    public function test_custom_prefix_is_used(): void
    {
        $factory = new EnemyFactory(new EnemyCatalog(EnemyFixtures::fullSet()), 'summon');

        self::assertSame('summon-1', $factory->create('zombie', Vector2::zero())->id());
    }

    private static function factory(): EnemyFactory
    {
        return new EnemyFactory(new EnemyCatalog(EnemyFixtures::fullSet()));
    }
}
