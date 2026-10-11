<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Ai;

use Error;
use InvalidArgumentException;
use Jogo\Domain\Ai\EntityView;
use Jogo\Domain\Ai\SpatialWorldView;
use Jogo\Domain\Ai\WorldEntity;
use Jogo\Domain\Ai\WorldEntityProvider;
use Jogo\Domain\Value\Vector2;
use Jogo\Tests\Support\FakeCombatWorld;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class SpatialWorldViewTest extends TestCase
{
    public function test_centers_boundary_zero_radius_order_and_fresh_snapshots(): void
    {
        $world = new FakeCombatWorld;
        $world->add('z', 100.0, 0.0, 30.0);
        $moving = $world->add('2', 10.0);
        $world->add('10', 0.0);
        $world->add('outside', 10.01, 0.0, 100.0);
        $inactive = $world->add('inactive', 0.0);
        $inactive->deactivate();
        $dead = $world->add('dead', 3.0);
        $dead->health()->damage(100.0);
        $view = new SpatialWorldView($world, static fn () => new Vector2(2.0, 3.0));
        self::assertTrue($view->playerPosition()->equals(new Vector2(2.0, 3.0)));
        $neighbors = $view->neighbors(Vector2::zero(), 10.0);
        self::assertSame(['10', '2', 'dead'], array_column($neighbors, 'id'));
        self::assertFalse($neighbors[2]->isAlive);
        self::assertSame(['10'], array_column($view->neighbors(Vector2::zero(), 0.0), 'id'));
        $moving->moveTo(new Vector2(300.0, 0.0));
        self::assertSame(10.0, $neighbors[1]->position->x());
        self::assertSame(['10', 'dead'], array_column($view->neighbors(Vector2::zero(), 10.0), 'id'));
        self::assertSame(['10', '2', 'dead', 'outside', 'z'], array_column($view->neighbors(Vector2::zero(), 400.0), 'id'));
    }

    public function test_snapshot_cannot_be_changed_and_exposes_only_ratified_data(): void
    {
        $view = new EntityView('enemy', Vector2::zero(), 1.0, 'enemy', true);
        self::assertSame(['id', 'position', 'collisionRadius', 'kind', 'isAlive'], array_keys(get_object_vars($view)));
        $this->expectException(Error::class);
        (new ReflectionProperty($view, 'isAlive'))->setValue($view, false);
    }

    public function test_multiple_cells_do_not_duplicate_ids_and_segment_candidates_include_collision_radius(): void
    {
        $world = new FakeCombatWorld;
        $world->add('large', 64.0, 6.0, 8.0);
        $view = new SpatialWorldView($world, static fn () => Vector2::zero());
        self::assertSame(['large'], array_column($view->candidates(new Vector2(0.0, 0.0), new Vector2(128.0, 0.0)), 'id'));
        self::assertSame(['large'], array_column($view->neighbors(Vector2::zero(), 200.0), 'id'));
    }

    public function test_duplicate_provider_ids_are_rejected_instead_of_overwritten(): void
    {
        $world = new FakeCombatWorld;
        $entity = $world->add('same', 0.0);
        $provider = new class($entity) implements WorldEntityProvider
        {
            public function __construct(private readonly FakeDamageable $entity) {}

            public function entities(): iterable
            {
                yield new WorldEntity($this->entity, 'enemy', true);
                yield new WorldEntity($this->entity, 'enemy', true);
            }
        };
        $this->expectException(InvalidArgumentException::class);
        (new SpatialWorldView($provider, static fn () => Vector2::zero()))->neighbors(Vector2::zero(), 10.0);
    }

    /** @return iterable<string, array{float}> */
    public static function invalidRadii(): iterable
    {
        yield 'negative' => [-1.0];
        yield 'nan' => [NAN];
        yield 'infinite' => [INF];
        yield 'negative infinite' => [-INF];
    }

    #[DataProvider('invalidRadii')]
    public function test_invalid_query_radius_is_rejected(float $radius): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SpatialWorldView(new FakeCombatWorld, static fn () => Vector2::zero()))->neighbors(Vector2::zero(), $radius);
    }

    /** @return iterable<string, array{string, float, string}> */
    public static function invalidViews(): iterable
    {
        yield 'id' => ['', 1.0, 'enemy'];
        yield 'radius' => ['a', -1.0, 'enemy'];
        yield 'infinite radius' => ['a', INF, 'enemy'];
        yield 'nan radius' => ['a', NAN, 'enemy'];
        yield 'kind' => ['a', 1.0, 'unknown'];
    }

    #[DataProvider('invalidViews')]
    public function test_invalid_snapshot_data_is_rejected(string $id, float $radius, string $kind): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EntityView($id, Vector2::zero(), $radius, $kind, true);
    }
}
