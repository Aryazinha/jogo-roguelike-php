<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\System;

use InvalidArgumentException;
use Jogo\Domain\System\CollisionSystem;
use Jogo\Domain\Value\Vector2;
use Jogo\Tests\Support\FakeCollidable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CollisionSystemTest extends TestCase
{
    #[DataProvider('circleCases')]
    public function test_circular_intersection_is_symmetric(float $x, float $y, float $firstRadius, float $secondRadius, bool $expected): void
    {
        $first = new FakeCollidable('first', Vector2::zero(), $firstRadius);
        $second = new FakeCollidable('second', new Vector2($x, $y), $secondRadius);
        $system = new CollisionSystem;

        self::assertSame($expected, $system->intersects($first, $second));
        self::assertSame($expected, $system->intersects($second, $first));
    }

    /** @return iterable<string, array{float, float, float, float, bool}> */
    public static function circleCases(): iterable
    {
        yield 'separados' => [11.0, 0.0, 4.0, 6.0, false];
        yield 'sobrepostos' => [9.0, 0.0, 4.0, 6.0, true];
        yield 'tangência externa' => [10.0, 0.0, 4.0, 6.0, true];
        yield 'tangência diagonal' => [3.0, 4.0, 2.0, 3.0, true];
        yield 'mesma posição' => [0.0, 0.0, 2.0, 3.0, true];
        yield 'contido' => [2.0, 0.0, 10.0, 1.0, true];
        yield 'pontos coincidentes' => [0.0, 0.0, 0.0, 0.0, true];
        yield 'pontos separados' => [1.0, 0.0, 0.0, 0.0, false];
        yield 'ponto na borda' => [-5.0, 0.0, 5.0, 0.0, true];
        yield 'ponto fora' => [-5.01, 0.0, 5.0, 0.0, false];
        yield 'pontos minúsculos separados' => [1.0E-200, 0.0, 0.0, 0.0, false];
        yield 'tangência minúscula' => [1.0E-200, 0.0, 0.5E-200, 0.5E-200, true];
        yield 'quadrados subnormais não confundem separação' => [2.0E-162, 0.0, 1.8E-162, 0.0, false];
    }

    public function test_inactive_entities_and_self_intersections_are_ignored_without_mutation(): void
    {
        $first = new FakeCollidable('first', Vector2::zero(), 10.0);
        $second = new FakeCollidable('second', Vector2::zero(), 10.0);
        $system = new CollisionSystem;
        $position = $first->position();
        self::assertTrue($system->intersects($first, $second));
        self::assertFalse($system->intersects($first, $first));
        self::assertFalse($system->intersects($first, new FakeCollidable('first', Vector2::zero(), 10.0)));
        $second->deactivate();
        self::assertFalse($system->intersects($first, $second));
        self::assertFalse($system->intersects($second, $first));
        self::assertSame($position, $first->position());
        self::assertTrue($first->isActive());
    }

    public function test_large_finite_coordinates_and_radii_do_not_cause_false_positives(): void
    {
        $first = new Vector2(-1.0E308, -1.0E308);
        $second = new Vector2(1.0E308, 1.0E308);

        self::assertFalse(CollisionSystem::circlesIntersect($first, 1.0E308, $second, 1.0E308));
        self::assertTrue(CollisionSystem::circlesIntersect(new Vector2(-1.0E308, 0.0), 1.0E308,
            new Vector2(1.0E308, 0.0), 1.0E308));
        self::assertFalse(CollisionSystem::circlesIntersect(new Vector2(1.0E308, 0.0), 0.0,
            new Vector2(1.0E308, 1.0E-200), 0.0));
    }

    #[DataProvider('invalidRadii')]
    public function test_it_rejects_invalid_radii_in_both_circles(float $radius, bool $first): void
    {
        $this->expectException(InvalidArgumentException::class);

        CollisionSystem::circlesIntersect(Vector2::zero(), $first ? $radius : 0.0,
            Vector2::zero(), $first ? 0.0 : $radius);
    }

    /** @return iterable<string, array{float, bool}> */
    public static function invalidRadii(): iterable
    {
        foreach ([-1.0, INF, -INF, NAN] as $index => $radius) {
            yield "primeiro $index" => [$radius, true];
            yield "segundo $index" => [$radius, false];
        }
    }

    #[DataProvider('nonFiniteCoordinates')]
    public function test_non_finite_coordinates_are_rejected_at_the_vector_boundary(float $x, float $y): void
    {
        $this->expectException(InvalidArgumentException::class);

        CollisionSystem::circlesIntersect(new Vector2($x, $y), 0.0, Vector2::zero(), 0.0);
    }

    /** @return iterable<string, array{float, float}> */
    public static function nonFiniteCoordinates(): iterable
    {
        foreach ([INF, -INF, NAN] as $index => $value) {
            yield "x $index" => [$value, 0.0];
            yield "y $index" => [0.0, $value];
        }
    }
}
