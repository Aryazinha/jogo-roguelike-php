<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\System;

use InvalidArgumentException;
use Jogo\Domain\System\CollisionSystem;
use Jogo\Domain\Value\Vector2;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SegmentCollisionTest extends TestCase
{
    /** @return iterable<string, array{float, float, float, ?float}> */
    public static function contacts(): iterable
    {
        yield 'first surface' => [5.0, 0.0, 1.0, 0.4];
        yield 'tangency' => [5.0, 1.0, 1.0, 0.5];
        yield 'endpoint' => [10.0, 0.0, 0.0, 1.0];
        yield 'inside at start' => [0.5, 0.0, 1.0, 0.0];
        yield 'point' => [5.0, 0.0, 0.0, 0.5];
        yield 'outside' => [5.0, 1.01, 1.0, null];
        yield 'behind' => [-2.0, 0.0, 1.0, null];
        yield 'beyond end' => [11.01, 0.0, 1.0, null];
    }

    #[DataProvider('contacts')]
    public function test_first_contact_along_the_entire_segment(float $x, float $y, float $radius, ?float $fraction): void
    {
        self::assertSame($fraction, CollisionSystem::segmentCircleFirstHit(Vector2::zero(), new Vector2(10.0, 0.0), new Vector2($x, $y), $radius));
    }

    public function test_zero_length_segment_only_hits_a_circle_that_contains_it(): void
    {
        self::assertSame(0.0, CollisionSystem::segmentCircleFirstHit(Vector2::zero(), Vector2::zero(), Vector2::zero(), 0.0));
        self::assertNull(CollisionSystem::segmentCircleFirstHit(Vector2::zero(), Vector2::zero(), new Vector2(1.0, 0.0), 0.0));
    }

    public function test_invalid_radius_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CollisionSystem::segmentCircleFirstHit(Vector2::zero(), Vector2::zero(), Vector2::zero(), NAN);
    }
}
