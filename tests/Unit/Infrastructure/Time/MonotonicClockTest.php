<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Infrastructure\Time;

use Jogo\Infrastructure\Time\MonotonicClock;
use PHPUnit\Framework\TestCase;

final class MonotonicClockTest extends TestCase
{
    public function test_it_returns_finite_non_negative_seconds_without_moving_backwards(): void
    {
        $clock = new MonotonicClock;
        $first = $clock->now();
        $second = $clock->now();

        self::assertTrue(is_finite($first));
        self::assertTrue(is_finite($second));
        self::assertGreaterThanOrEqual(0.0, $first);
        self::assertGreaterThanOrEqual($first, $second);
    }
}
