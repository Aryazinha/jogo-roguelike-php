<?php

declare(strict_types=1);

namespace Jogo\Tests\Benchmark;

use Jogo\Domain\Spatial\SpatialHash;
use Jogo\Domain\System\CollisionSystem;
use Jogo\Domain\System\GeometryMetrics;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\AttackGeometry;
use Jogo\Domain\Weapon\Targeting\TargetingContext;
use Jogo\Tests\Support\FakeCollidable;
use PHPUnit\Framework\TestCase;

final class GeometryMetricsTest extends TestCase
{
    public function test_effective_geometry_is_counted_without_changing_results(): void
    {
        $metrics = new GeometryMetrics;
        $origin = Vector2::zero();
        self::assertSame(CollisionSystem::circlesIntersect($origin, 1.0, $origin, 1.0), CollisionSystem::circlesIntersect($origin, 1.0, $origin, 1.0, $metrics));
        self::assertSame(1, $metrics->snapshot()->circles);
        self::assertSame(0.0, CollisionSystem::segmentCircleFirstHit($origin, new Vector2(2.0, 0.0), $origin, 1.0, $metrics));
        self::assertSame(2, $metrics->snapshot()->circles);
        self::assertSame(1, $metrics->snapshot()->segments);
        $metrics->beginStep();
        self::assertSame(0, $metrics->snapshot()->narrowTests());
        $grid = new SpatialHash(metrics: $metrics);
        $grid->rebuild([new FakeCollidable('enemy', $origin, 1.0)]);
        self::assertCount(1, $grid->queryRadius($origin, 2.0)->entities);
        self::assertSame(1, $metrics->snapshot()->rebuilds);
        self::assertSame(1, $metrics->snapshot()->candidates);
        self::assertSame(1, $metrics->snapshot()->narrowTests());
        self::assertTrue(AttackGeometry::inSector(new TargetingContext($origin, new Vector2(1.0, 0.0), 10.0), new Vector2(1.0, 0.0), 90.0, $metrics));
        self::assertSame(1, $metrics->snapshot()->sectors);
        self::assertSame(3, $metrics->snapshot()->narrowTests());
    }
}
