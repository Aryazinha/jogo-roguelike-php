<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Weapon\Targeting;

use InvalidArgumentException;
use Jogo\Domain\Ai\EntityView;
use Jogo\Domain\Ai\WorldView;
use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\Targeting\FacingTargeting;
use Jogo\Domain\Weapon\Targeting\NearestEnemyTargeting;
use Jogo\Domain\Weapon\Targeting\RandomEnemyTargeting;
use Jogo\Domain\Weapon\Targeting\SurroundingTargeting;
use Jogo\Domain\Weapon\Targeting\TargetingContext;
use Jogo\Domain\Weapon\Targeting\TargetingResult;
use Jogo\Tests\Support\ControlledRandomIndex;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class TargetingTest extends TestCase
{
    /** @return iterable<string, array{float, float, float}> */
    public static function invalidContexts(): iterable
    {
        yield 'zero facing' => [0.0, 0.0, 10.0];
        yield 'nonunit facing' => [2.0, 1.0, 10.0];
        yield 'overflow length' => [PHP_FLOAT_MAX, PHP_FLOAT_MAX, 10.0];
        foreach (['zero' => 0.0, 'negative' => -1.0, 'nan' => NAN, 'inf' => INF, 'negative inf' => -INF] as $name => $range) {
            yield $name => [1.0, 0.0, $range];
        }
    }

    #[DataProvider('invalidContexts')]
    public function test_context_rejects_invalid_facing_or_range(float $x, float $y, float $range): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TargetingContext(Vector2::zero(), new Vector2($x, $y), $range);
    }

    public function test_facing_and_area_geometry_without_entities(): void
    {
        $context = new TargetingContext(new Vector2(3.0, 4.0), new Vector2(0.0, -1.0), 10.0);
        $world = self::world([]);
        $facing = (new FacingTargeting)->select($context, $world);
        self::assertSame($context->facing, $facing->direction);
        self::assertTrue($facing->position->equals(new Vector2(3.0, -6.0)));
        self::assertNull($facing->entityId);
        $area = (new SurroundingTargeting)->select($context, $world);
        self::assertSame($context->origin, $area->position);
        self::assertTrue($area->direction->equals(Vector2::zero()));
        self::assertNull($area->entityId);
    }

    public function test_nearest_filters_invalid_snapshots_includes_boundary_and_breaks_ties_by_lexical_id(): void
    {
        $context = new TargetingContext(Vector2::zero(), new Vector2(1.0, 0.0), 10.0);
        $snapshots = [self::enemy('z', 10.0), self::enemy('2', -10.0), self::enemy('10', 10.0),
            self::enemy('dead', 1.0, false), self::enemy('unknown-life', 1.0, null),
            new EntityView('pickup', Vector2::zero(), 1.0, 'pickup', null), self::enemy('outside', 10.01)];
        $result = (new NearestEnemyTargeting)->select($context, self::world($snapshots));
        self::assertNotNull($result);
        self::assertSame('10', $result->entityId);
        self::assertTrue($result->direction->equals(new Vector2(1.0, 0.0)));
        self::assertSame($snapshots[2]->position, $result->position);
        self::assertSame($result->entityId, (new NearestEnemyTargeting)->select($context, self::world(array_reverse($snapshots)))?->entityId);
    }

    public function test_overlapping_target_uses_facing_and_keeps_the_selected_position_snapshot(): void
    {
        $context = new TargetingContext(Vector2::zero(), new Vector2(0.0, 1.0), 10.0);
        foreach ([new NearestEnemyTargeting, new RandomEnemyTargeting(new ControlledRandomIndex)] as $strategy) {
            $result = $strategy->select($context, self::world([self::enemy('same-point', 0.0)]));
            self::assertNotNull($result);
            self::assertSame($context->facing, $result->direction);
            self::assertTrue($result->position->equals(Vector2::zero()));
        }
    }

    public function test_empty_selection_does_not_consume_random_state(): void
    {
        $context = new TargetingContext(Vector2::zero(), new Vector2(1.0, 0.0), 10.0);
        $random = new ControlledRandomIndex;
        $world = self::world([self::enemy('dead', 0.0, false)]);
        self::assertNull((new NearestEnemyTargeting)->select($context, $world));
        self::assertNull((new RandomEnemyTargeting($random))->select($context, $world));
        self::assertSame([], $random->counts);
    }

    public function test_random_candidates_are_sorted_and_filtered_before_injected_draw(): void
    {
        $context = new TargetingContext(Vector2::zero(), new Vector2(1.0, 0.0), 10.0);
        $random = new ControlledRandomIndex(1);
        $strategy = new RandomEnemyTargeting($random);
        $views = [self::enemy('z', 9.0), self::enemy('a', 10.0), self::enemy('outside', 10.1), self::enemy('dead', 0.0, false)];
        self::assertSame('z', $strategy->select($context, self::world($views))?->entityId);
        self::assertSame('z', $strategy->select($context, self::world(array_reverse($views)))?->entityId);
        self::assertSame([2, 2], $random->counts);
    }

    public function test_same_seed_and_world_reproduce_the_entire_selection_sequence(): void
    {
        $context = new TargetingContext(Vector2::zero(), new Vector2(1.0, 0.0), 100.0);
        $views = [self::enemy('z', 90.0), self::enemy('a', 2.0), self::enemy('b', 10.0)];
        $first = new RandomEnemyTargeting(new SeededRandomIndex(314));
        $second = new RandomEnemyTargeting(new SeededRandomIndex(314));
        $selected = [];
        for ($index = 0; $index < 80; $index++) {
            $id = $first->select($context, self::world($views))?->entityId;
            $selected[] = $id;
            self::assertSame($id, $second->select($context, self::world(array_reverse($views)))?->entityId);
        }
        self::assertGreaterThan(1, count(array_unique($selected)));
    }

    public function test_out_of_bounds_random_index_is_a_clear_provider_error(): void
    {
        $this->expectException(UnexpectedValueException::class);
        (new RandomEnemyTargeting(new ControlledRandomIndex(10)))->select(
            new TargetingContext(Vector2::zero(), new Vector2(1.0, 0.0), 10.0), self::world([self::enemy('a', 1.0)]));
    }

    public function test_seeded_generator_rejects_empty_draw_and_single_candidate_is_zero(): void
    {
        $random = new SeededRandomIndex(0);
        self::assertSame(0, $random->nextIndex(1));
        $this->expectException(InvalidArgumentException::class);
        $random->nextIndex(0);
    }

    /** @return iterable<string, array{float, float, ?string}> */
    public static function invalidResults(): iterable
    {
        yield 'direction' => [2.0, 0.0, null];
        yield 'zero individual direction' => [0.0, 0.0, 'enemy'];
        yield 'blank id' => [1.0, 0.0, ' '];
    }

    #[DataProvider('invalidResults')]
    public function test_result_rejects_invalid_direction_or_id(float $x, float $y, ?string $id): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TargetingResult(new Vector2($x, $y), Vector2::zero(), $id);
    }

    private static function enemy(string $id, float $x, ?bool $alive = true): EntityView
    {
        return new EntityView($id, new Vector2($x, 0.0), 0.0, 'enemy', $alive);
    }

    /** @param list<EntityView> $views */
    private static function world(array $views): WorldView
    {
        return new class($views) implements WorldView
        {
            /** @param list<EntityView> $views */
            public function __construct(private readonly array $views) {}

            public function playerPosition(): Vector2
            {
                return Vector2::zero();
            }

            public function neighbors(Vector2 $center, float $radius): array
            {
                return $this->views;
            }
        };
    }
}
