<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Enemy;

use InvalidArgumentException;
use Jogo\Domain\Enemy\EnemyArchetype;
use Jogo\Domain\Enemy\EnemyCategory;
use Jogo\Domain\Enemy\RangedSettings;
use Jogo\Domain\Enemy\SplitRule;
use Jogo\Tests\Support\EnemyFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EnemyArchetypeTest extends TestCase
{
    public function test_common_enemy_keeps_the_values_it_received(): void
    {
        $zombie = EnemyFixtures::common('zombie', 6);

        self::assertSame('zombie', $zombie->id);
        self::assertSame(EnemyCategory::COMMON, $zombie->category);
        self::assertSame([13.0, 3.0, 3, 4, 6], [$zombie->maxHealth, $zombie->damage, $zombie->xpMin,
            $zombie->xpMax, $zombie->minLevel]);
        self::assertFalse($zombie->isBoss());
    }

    public function test_spawn_respects_the_minimum_level(): void
    {
        $zombie = EnemyFixtures::common('zombie', 6);

        self::assertFalse($zombie->canSpawnAtLevel(5));
        self::assertTrue($zombie->canSpawnAtLevel(6));
        self::assertTrue($zombie->canSpawnAtLevel(30));
    }

    public function test_boss_and_split_children_never_enter_the_regular_spawn(): void
    {
        self::assertTrue(EnemyFixtures::boss()->isBoss());
        self::assertFalse(EnemyFixtures::boss()->canSpawnAtLevel(99));
        self::assertFalse(EnemyFixtures::smallSlime()->canSpawnAtLevel(99));
        self::assertTrue(EnemyFixtures::slime()->canSpawnAtLevel(8));
    }

    public function test_ranged_settings_expose_the_hysteresis_band(): void
    {
        $ranged = EnemyFixtures::skeleton()->ranged;

        self::assertNotNull($ranged);
        self::assertSame(160.0, $ranged->minDistance());
        self::assertSame(240.0, $ranged->maxDistance());
    }

    /** @return iterable<string, array{callable(): mixed}> */
    public static function invalidArchetypes(): iterable
    {
        yield 'zero health' => [static fn () => self::make(maxHealth: 0.0)];
        yield 'negative damage' => [static fn () => self::make(damage: -1.0)];
        yield 'xp max below min' => [static fn () => self::make(xpMin: 5, xpMax: 4)];
        yield 'negative xp' => [static fn () => self::make(xpMin: -1)];
        yield 'zero radius' => [static fn () => self::make(collisionRadius: 0.0)];
        yield 'unknown behavior' => [static fn () => self::make(behaviorId: 'voar')];
        yield 'common without level' => [static fn () => self::make(minLevel: null)];
        yield 'common with level zero' => [static fn () => self::make(minLevel: 0)];
        yield 'common with spawn time' => [static fn () => self::make(spawnAtSeconds: 10.0)];
        yield 'common with boss behavior' => [static fn () => self::make(behaviorId: EnemyArchetype::BEHAVIOR_BOSS)];
        yield 'common that splits' => [static fn () => self::make(split: new SplitRule('other', 2))];
        yield 'ranged without settings' => [static fn () => self::make(behaviorId: EnemyArchetype::BEHAVIOR_KEEP_DISTANCE)];
        yield 'settings without ranged' => [static fn () => self::make(ranged: new RangedSettings(200.0, 40.0, 2.0))];
        yield 'boss with level' => [static fn () => new EnemyArchetype('b', 'B', EnemyCategory::BOSS, 10.0, 1.0, 1, 1,
            1.0, 1.0, EnemyArchetype::BEHAVIOR_BOSS, minLevel: 1, spawnAtSeconds: 600.0)];
        yield 'boss without spawn time' => [static fn () => new EnemyArchetype('b', 'B', EnemyCategory::BOSS, 10.0, 1.0,
            1, 1, 1.0, 1.0, EnemyArchetype::BEHAVIOR_BOSS)];
        yield 'boss chasing' => [static fn () => new EnemyArchetype('b', 'B', EnemyCategory::BOSS, 10.0, 1.0, 1, 1,
            1.0, 1.0, EnemyArchetype::BEHAVIOR_CHASE, spawnAtSeconds: 600.0)];
        yield 'special splitting into itself' => [static fn () => new EnemyArchetype('s', 'S', EnemyCategory::SPECIAL,
            10.0, 1.0, 1, 1, 1.0, 1.0, EnemyArchetype::BEHAVIOR_CHASE, minLevel: 1, split: new SplitRule('s', 2))];
        yield 'split with zero children' => [static fn () => new SplitRule('child', 0)];
        yield 'hysteresis wider than distance' => [static fn () => new RangedSettings(40.0, 40.0, 2.0)];
        yield 'zero shot cooldown' => [static fn () => new RangedSettings(200.0, 40.0, 0.0)];
    }

    /** @param callable(): mixed $build */
    #[DataProvider('invalidArchetypes')]
    public function test_invalid_data_is_rejected(callable $build): void
    {
        $this->expectException(InvalidArgumentException::class);

        $build();
    }

    private static function make(
        float $maxHealth = 10.0,
        float $damage = 1.0,
        int $xpMin = 1,
        int $xpMax = 2,
        float $collisionRadius = 10.0,
        string $behaviorId = EnemyArchetype::BEHAVIOR_CHASE,
        ?int $minLevel = 1,
        ?float $spawnAtSeconds = null,
        ?SplitRule $split = null,
        ?RangedSettings $ranged = null,
    ): EnemyArchetype {
        return new EnemyArchetype('test', 'Test', EnemyCategory::COMMON, $maxHealth, $damage, $xpMin, $xpMax, 50.0,
            $collisionRadius, $behaviorId, $minLevel, $spawnAtSeconds, $split, false, $ranged);
    }
}
