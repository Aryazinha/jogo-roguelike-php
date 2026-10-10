<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Spatial;

use InvalidArgumentException;
use Jogo\Domain\Entity\Collidable;
use Jogo\Domain\Spatial\SpatialHash;
use Jogo\Domain\Spatial\SpatialQueryResult;
use Jogo\Domain\Value\Vector2;
use Jogo\Tests\Support\FakeCollidable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpatialHashTest extends TestCase
{
    public function test_an_empty_grid_and_an_entity_inserted_in_one_cell(): void
    {
        $grid = new SpatialHash;
        self::assertSame(64.0, $grid->cellSize());
        self::assertSame(0, $grid->entityCount());
        self::assertSame([], $grid->queryNeighbors(Vector2::zero())->entities);
        self::assertSame([], $grid->queryRadius(Vector2::zero(), 0.0)->entities);

        $entity = new FakeCollidable('a', new Vector2(20.0, 20.0), 4.0);
        $grid->insert($entity);
        $query = $grid->queryNeighbors(new Vector2(32.0, 32.0));
        self::assertSame([$entity], $query->entities);
        self::assertSame(1, $query->candidatesExamined);
        self::assertSame(1, $query->resultCount());
        self::assertSame(9, $query->cellsVisited);
        self::assertSame(1, $grid->entityCount());
    }

    public function test_it_visits_exactly_the_nine_neighboring_cells(): void
    {
        $grid = new SpatialHash;
        $expected = [];

        for ($x = -1; $x <= 1; $x++) {
            for ($y = -1; $y <= 1; $y++) {
                $entity = new FakeCollidable("$x:$y", new Vector2($x * 64.0 + 32.0, $y * 64.0 + 32.0), 0.0);
                $grid->insert($entity);
                $expected[] = $entity->id();
            }
        }

        $grid->insert(new FakeCollidable('outside', new Vector2(128.0, 32.0), 0.0));
        sort($expected, SORT_STRING);
        $query = $grid->queryNeighbors(new Vector2(32.0, 32.0));

        self::assertSame($expected, $this->ids($query));
        self::assertSame(9, $query->candidatesExamined);
        self::assertSame(9, $query->cellsVisited);
    }

    public function test_floor_assigns_negative_coordinates_to_the_correct_cells(): void
    {
        $grid = new SpatialHash;
        $grid->insert(new FakeCollidable('negative', new Vector2(-0.1, -0.1), 0.0));
        $grid->insert(new FakeCollidable('zero', Vector2::zero(), 0.0));

        // Centro na célula (-2, -2): alcança (-1, -1), mas não (0, 0).
        self::assertSame(['negative'], $this->ids($grid->queryNeighbors(new Vector2(-96.0, -96.0))));
        self::assertSame(['negative'], $this->ids($grid->queryRadius(new Vector2(-0.1, -0.1), 0.0)));
        self::assertSame(['zero'], $this->ids($grid->queryRadius(Vector2::zero(), 0.0)));
    }

    public function test_circles_crossing_cell_edges_are_not_lost(): void
    {
        $grid = new SpatialHash;
        $entity = new FakeCollidable('edge', new Vector2(63.0, 32.0), 4.0);
        $grid->insert($entity);

        self::assertSame(['edge'], $this->ids($grid->queryRadius(new Vector2(67.0, 32.0), 0.0)));
        self::assertSame(['edge'], $this->ids($grid->queryRadius(new Vector2(59.0, 32.0), 0.0)));
        self::assertSame([], $grid->queryRadius(new Vector2(67.01, 32.0), 0.0)->entities);
        self::assertSame(1, $grid->queryNeighbors(new Vector2(64.0, 32.0))->candidatesExamined);

        $grid->insert(new FakeCollidable('tangent-edge', new Vector2(-4.0, 32.0), 4.0));
        self::assertSame(['tangent-edge'], $this->ids($grid->queryRadius(new Vector2(0.0, 32.0), 0.0)));
    }

    public function test_a_large_circle_is_indexed_in_multiple_cells_and_returned_once(): void
    {
        $grid = new SpatialHash;
        $entity = new FakeCollidable('large', Vector2::zero(), 160.0);
        $grid->insert($entity);

        self::assertSame([$entity], $grid->queryRadius(new Vector2(160.0, 0.0), 0.0)->entities);
        self::assertSame([$entity], $grid->queryNeighbors(new Vector2(-160.0, 0.0))->entities);
        $query = $grid->queryRadius(Vector2::zero(), 160.0);
        self::assertSame([$entity], $query->entities);
        self::assertSame(1, $query->candidatesExamined);
        self::assertSame(1, $query->resultCount());
        self::assertSame(36, $query->cellsVisited);
    }

    public function test_radius_queries_confirm_geometry_beyond_nine_cells(): void
    {
        $grid = new SpatialHash;
        $grid->insert(new FakeCollidable('center-outside', new Vector2(130.0, 0.0), 10.0));
        $grid->insert(new FakeCollidable('point', new Vector2(120.0, 0.0), 0.0));
        $grid->insert(new FakeCollidable('square-corner', new Vector2(119.0, 119.0), 0.0));
        $grid->insert(new FakeCollidable('far', new Vector2(300.0, 0.0), 0.0));

        $query = $grid->queryRadius(Vector2::zero(), 120.0);
        self::assertSame(['center-outside', 'point'], $this->ids($query));
        self::assertSame(3, $query->candidatesExamined);
        self::assertSame(2, $query->resultCount());
        self::assertSame(16, $query->cellsVisited);
    }

    public function test_zero_radius_includes_circles_covering_the_point_not_only_coincident_centers(): void
    {
        $grid = new SpatialHash;
        $grid->rebuild([
            new FakeCollidable('point', Vector2::zero(), 0.0),
            new FakeCollidable('circle', new Vector2(5.0, 0.0), 5.0),
            new FakeCollidable('separated', new Vector2(6.0, 0.0), 5.0),
        ]);

        self::assertSame(['circle', 'point'], $this->ids($grid->queryRadius(Vector2::zero(), 0.0)));
    }

    public function test_rebuild_updates_moved_positions_and_clear_resets_the_grid(): void
    {
        $entity = new FakeCollidable('moving', Vector2::zero(), 2.0);
        $grid = new SpatialHash;
        $grid->insert($entity);
        $entity->moveTo(new Vector2(256.0, 256.0));
        $grid->rebuild([$entity]);

        self::assertSame([], $grid->queryNeighbors(Vector2::zero())->entities);
        self::assertSame([$entity], $grid->queryRadius(new Vector2(256.0, 256.0), 0.0)->entities);
        $grid->clear();
        self::assertSame(0, $grid->entityCount());
        self::assertSame([], $grid->queryRadius(new Vector2(256.0, 256.0), 10.0)->entities);
        $grid->insert($entity);
        $grid->rebuild([]);
        self::assertSame(0, $grid->entityCount());
    }

    public function test_inactive_entities_are_ignored_at_insertion_and_at_query_time(): void
    {
        $before = new FakeCollidable('before', Vector2::zero(), 1.0);
        $before->deactivate();
        $after = new FakeCollidable('after', Vector2::zero(), 1.0);
        $grid = new SpatialHash;
        $grid->rebuild([$before, $after]);
        self::assertSame(1, $grid->entityCount());
        $after->deactivate();
        self::assertSame(0, $grid->queryNeighbors(Vector2::zero())->candidatesExamined);
        self::assertSame([], $grid->queryRadius(Vector2::zero(), 10.0)->entities);
        $grid->rebuild([$before, $after]);
        self::assertSame(0, $grid->entityCount());
    }

    public function test_order_is_lexical_by_id_independent_of_insertion_order_and_self_can_be_excluded(): void
    {
        $entities = [new FakeCollidable('2', Vector2::zero(), 70.0),
            new FakeCollidable('10', Vector2::zero(), 70.0), new FakeCollidable('0', Vector2::zero(), 70.0)];
        $first = new SpatialHash;
        $second = new SpatialHash;
        $first->rebuild($entities);
        $second->rebuild(array_reverse($entities));

        self::assertSame(['0', '10', '2'], $this->ids($first->queryNeighbors(Vector2::zero())));
        self::assertSame($first->queryRadius(Vector2::zero(), 100.0)->entities,
            $second->queryRadius(Vector2::zero(), 100.0)->entities);
        $query = $first->queryRadius(Vector2::zero(), 0.0, excludeId: '0');
        self::assertSame(['10', '2'], $this->ids($query));
        self::assertSame(2, $query->candidatesExamined);
        self::assertSame(['10', '2'], $this->ids($first->queryNeighbors(Vector2::zero(), excludeId: '0')));
    }

    public function test_duplicate_ids_do_not_replace_entities_or_partially_rebuild(): void
    {
        $original = new FakeCollidable('same', Vector2::zero(), 1.0);
        $duplicate = new FakeCollidable('same', new Vector2(256.0, 0.0), 1.0);
        $grid = new SpatialHash;
        $grid->insert($original);

        foreach ([[$original], [$duplicate]] as $attempt) {
            try {
                $grid->insert($attempt[0]);
                self::fail('Inserção duplicada deveria ser rejeitada.');
            } catch (InvalidArgumentException) {
                self::assertSame([$original], $grid->queryRadius(Vector2::zero(), 0.0)->entities);
                self::assertSame(1, $grid->entityCount());
            }
        }

        try {
            $grid->rebuild([$original, $duplicate]);
            self::fail('Reconstrução duplicada deveria ser rejeitada.');
        } catch (InvalidArgumentException) {
            self::assertSame([$original], $grid->queryRadius(Vector2::zero(), 0.0)->entities);
            self::assertSame([], $grid->queryNeighbors(new Vector2(256.0, 0.0))->entities);
        }
    }

    public function test_cell_size_is_configurable_without_changing_geometry(): void
    {
        $grid = new SpatialHash(10.0);
        $entity = new FakeCollidable('a', new Vector2(21.0, 0.0), 2.0);
        $grid->insert($entity);

        self::assertSame(10.0, $grid->cellSize());
        self::assertSame([$entity], $grid->queryRadius(new Vector2(19.0, 0.0), 0.0)->entities);
        self::assertSame([$entity], $grid->queryNeighbors(Vector2::zero())->entities);
        self::assertSame([], $grid->queryRadius(Vector2::zero(), 0.0)->entities);
    }

    #[DataProvider('invalidCellSizes')]
    public function test_it_rejects_invalid_cell_sizes(float $size): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SpatialHash($size);
    }

    /** @return iterable<string, array{float}> */
    public static function invalidCellSizes(): iterable
    {
        foreach ([0.0, -1.0, INF, -INF, NAN] as $index => $size) {
            yield "tamanho $index" => [$size];
        }
    }

    #[DataProvider('invalidRadii')]
    public function test_radius_queries_reject_invalid_radii(float $radius): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SpatialHash)->queryRadius(Vector2::zero(), $radius);
    }

    /** @return iterable<string, array{float}> */
    public static function invalidRadii(): iterable
    {
        foreach ([-1.0, INF, -INF, NAN] as $index => $radius) {
            yield "raio $index" => [$radius];
        }
    }

    #[DataProvider('invalidEntityData')]
    public function test_it_validates_other_implementations_of_collidable(string $id, float $radius): void
    {
        $this->expectException(InvalidArgumentException::class);
        $entity = new class($id, $radius) implements Collidable
        {
            public function __construct(private string $identifier, private float $radius) {}

            public function id(): string
            {
                return $this->identifier;
            }

            public function position(): Vector2
            {
                return Vector2::zero();
            }

            public function collisionRadius(): float
            {
                return $this->radius;
            }

            public function isActive(): bool
            {
                return true;
            }
        };

        (new SpatialHash)->insert($entity);
    }

    /** @return iterable<string, array{string, float}> */
    public static function invalidEntityData(): iterable
    {
        yield 'ID vazio' => ['', 0.0];
        yield 'ID em branco' => ['   ', 0.0];

        foreach ([-1.0, INF, -INF, NAN] as $index => $radius) {
            yield "raio $index" => ['invalid', $radius];
        }
    }

    public function test_unrepresentable_indices_fail_before_inserting(): void
    {
        $grid = new SpatialHash;

        try {
            $grid->insert(new FakeCollidable('extreme', new Vector2(PHP_FLOAT_MAX, 0.0), 0.0));
            self::fail('Índice fora da faixa deveria ser rejeitado.');
        } catch (InvalidArgumentException) {
            self::assertSame(0, $grid->entityCount());
        }
    }

    public function test_the_cell_budget_is_validated_and_can_be_configured(): void
    {
        $grid = new SpatialHash(maxCellsPerOperation: 4);
        $entity = new FakeCollidable('wide', new Vector2(32.0, 32.0), 64.0);

        try {
            $grid->insert($entity);
            self::fail('Uma expansão acima do orçamento deveria ser rejeitada.');
        } catch (InvalidArgumentException) {
            self::assertSame(0, $grid->entityCount());
        }

        $expanded = new SpatialHash(maxCellsPerOperation: 9);
        $expanded->insert($entity);
        self::assertSame([$entity], $expanded->queryRadius(new Vector2(32.0, 32.0), 64.0)->entities);

        $this->expectException(InvalidArgumentException::class);
        $grid->queryNeighbors(Vector2::zero());
    }

    #[DataProvider('invalidBudgets')]
    public function test_it_rejects_invalid_cell_budgets(int $budget): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SpatialHash(maxCellsPerOperation: $budget);
    }

    /** @return iterable<string, array{int}> */
    public static function invalidBudgets(): iterable
    {
        yield 'zero' => [0];
        yield 'negativo' => [-1];
    }

    /** @return list<string> */
    private function ids(SpatialQueryResult $query): array
    {
        return array_map(static fn (Collidable $entity): string => $entity->id(), $query->entities);
    }
}
