<?php

declare(strict_types=1);

namespace Jogo\Tests\Benchmark;

use Jogo\Domain\Ai\SpatialWorldView;
use Jogo\Domain\Ai\WorldEntityProvider;
use Jogo\Domain\Spatial\SpatialHash;
use Jogo\Domain\System\GeometryMetrics;
use Jogo\Domain\Value\Vector2;
use Jogo\Tests\Support\FakeCombatWorld;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PreparedWorldViewTest extends TestCase
{
    public function test_reuse_is_explicit_and_invalidation_restores_fresh_life_and_position(): void
    {
        $world = new FakeCombatWorld;
        $enemy = $world->add('target', 1.0);
        $metrics = new GeometryMetrics;
        $grid = new SpatialHash(metrics: $metrics);
        $view = new SpatialWorldView($world, static fn () => Vector2::zero(), $grid, $metrics);
        $view->refresh();
        $first = $view->neighbors(Vector2::zero(), 10.0);
        self::assertTrue($first[0]->isAlive);
        self::assertCount(1, $view->candidates(Vector2::zero(), new Vector2(2.0, 0.0)));
        self::assertSame(1, $metrics->snapshot()->rebuilds);
        $enemy->health()->damage(100.0);
        $enemy->moveTo(new Vector2(200.0, 0.0));
        $view->invalidate();
        self::assertSame([], $view->neighbors(Vector2::zero(), 10.0));
        self::assertFalse($view->neighbors(Vector2::zero(), 300.0)[0]->isAlive);
        self::assertSame(3, $metrics->snapshot()->rebuilds);
        self::assertSame(1.0, $first[0]->position->x());
        $view->refresh();
        $enemy->deactivate();
        $view->invalidate();
        self::assertSame([], $view->neighbors(Vector2::zero(), 300.0));
    }

    public function test_failed_refresh_does_not_restore_stale_preparation(): void
    {
        $world = new FakeCombatWorld;
        $enemy = $world->add('target', 1.0);
        $provider = new class($world) implements WorldEntityProvider
        {
            public bool $fail = false;

            public function __construct(private readonly FakeCombatWorld $world) {}

            public function entities(): iterable
            {
                if ($this->fail) {
                    throw new RuntimeException('provider indisponível');
                }
                yield from $this->world->entities();
            }
        };
        $view = new SpatialWorldView($provider, static fn () => Vector2::zero());
        $view->refresh();
        $provider->fail = true;
        try {
            $view->refresh();
            self::fail('A falha do provider deve ser propagada.');
        } catch (RuntimeException $error) {
            self::assertSame('provider indisponível', $error->getMessage());
        }
        $enemy->moveTo(new Vector2(200.0, 0.0));
        $provider->fail = false;
        self::assertSame([], $view->neighbors(Vector2::zero(), 10.0));
    }
}
