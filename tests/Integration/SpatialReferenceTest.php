<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Domain\Entity\Collidable;
use Jogo\Domain\Spatial\SpatialHash;
use Jogo\Domain\Spatial\SpatialQueryResult;
use Jogo\Domain\Value\Vector2;
use Jogo\Tests\Support\FakeCollidable;
use PHPUnit\Framework\TestCase;

final class SpatialReferenceTest extends TestCase
{
    public function test_300_entities_stay_below_40_candidates_with_exact_reproducible_metrics(): void
    {
        $entities = $this->referenceEntities();
        $grid = new SpatialHash;
        $grid->rebuild($entities);
        $neighborCandidates = 0;
        $radiusCandidates = 0;
        $radiusMatches = 0;
        $maximumNeighbors = 0;
        $maximumRadius = 0;
        self::assertSame(300, $grid->entityCount());

        foreach ($entities as $index => $entity) {
            $neighbors = $grid->queryNeighbors($entity->position(), $entity->id());
            $radius = $grid->queryRadius($entity->position(), 32.0, $entity->id());
            $neighborCandidates += $neighbors->candidatesExamined;
            $radiusCandidates += $radius->candidatesExamined;
            $radiusMatches += $radius->resultCount();
            $maximumNeighbors = max($maximumNeighbors, $neighbors->candidatesExamined);
            $maximumRadius = max($maximumRadius, $radius->candidatesExamined);
            self::assertSame(9, $neighbors->cellsVisited);
            self::assertSame(4, $radius->cellsVisited);
            self::assertSame($neighbors->candidatesExamined, $neighbors->resultCount());

            // Oráculo independente: só os quatro centros ortogonais a 32 px intersectam a área.
            $column = $index % 20;
            $row = intdiv($index, 20);
            $expected = [];

            foreach ([[$column - 1, $row], [$column + 1, $row], [$column, $row - 1], [$column, $row + 1]] as [$x, $y]) {
                if ($x >= 0 && $x < 20 && $y >= 0 && $y < 15) {
                    $expected[] = sprintf('entity-%03d', $y * 20 + $x);
                }
            }

            sort($expected, SORT_STRING);
            self::assertSame($expected, $this->ids($radius));
        }

        self::assertSame(8772, $neighborCandidates);
        self::assertSame(3956, $radiusCandidates);
        self::assertSame(1130, $radiusMatches);
        self::assertSame(35, $maximumNeighbors);
        self::assertSame(15, $maximumRadius);
        self::assertEqualsWithDelta(29.24, $neighborCandidates / 300, 1.0E-9);
        self::assertLessThan(40.0, $neighborCandidates / 300);
        self::assertLessThan(40.0, $radiusCandidates / 300);
    }

    public function test_grid_results_match_an_independent_brute_force_oracle_for_varied_circles(): void
    {
        $entities = [];

        for ($index = 0; $index < 80; $index++) {
            $entity = new FakeCollidable(sprintf('mixed-%03d', $index),
                new Vector2(($index * 97) % 401 - 200.0, ($index * 73) % 389 - 194.0),
                (float) (($index * 17) % 85));

            if ($index % 11 === 0) {
                $entity->deactivate();
            }

            $entities[] = $entity;
        }

        $grid = new SpatialHash;
        $grid->rebuild($entities);

        foreach ($entities as $source) {
            foreach ([0.0, 4.0, 63.0, 129.0] as $radius) {
                $expected = [];

                foreach ($entities as $target) {
                    if ($target->isActive() && $target->id() !== $source->id()
                        && hypot($target->position()->x() - $source->position()->x(),
                            $target->position()->y() - $source->position()->y()) <= $radius + $target->collisionRadius()) {
                        $expected[] = $target->id();
                    }
                }

                sort($expected, SORT_STRING);
                $query = $grid->queryRadius($source->position(), $radius, $source->id());
                self::assertSame($expected, $this->ids($query));
                self::assertGreaterThanOrEqual($query->resultCount(), $query->candidatesExamined);
                self::assertSame($query->entities, $grid->queryRadius($source->position(), $radius, $source->id())->entities);
            }
        }
    }

    /** @return list<FakeCollidable> */
    private function referenceEntities(): array
    {
        $entities = [];

        for ($row = 0; $row < 15; $row++) {
            for ($column = 0; $column < 20; $column++) {
                $entities[] = new FakeCollidable(sprintf('entity-%03d', $row * 20 + $column),
                    new Vector2($column * 32.0 - 304.0, $row * 32.0 - 240.0), 6.0);
            }
        }

        return $entities;
    }

    /** @return list<string> */
    private function ids(SpatialQueryResult $query): array
    {
        return array_map(static fn (Collidable $entity): string => $entity->id(), $query->entities);
    }
}
