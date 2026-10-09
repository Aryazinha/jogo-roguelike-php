<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Value;

use InvalidArgumentException;
use Jogo\Domain\Value\Health;
use PHPUnit\Framework\TestCase;

final class HealthTest extends TestCase
{
    public function test_it_starts_full_by_default(): void
    {
        $health = new Health(20.0);

        self::assertSame(20.0, $health->current());
        self::assertSame(20.0, $health->maximum());
        self::assertSame(1.0, $health->ratio());
        self::assertFalse($health->isDead());
    }

    public function test_damage_never_reduces_health_below_zero(): void
    {
        $health = new Health(20.0);

        self::assertSame(7.5, $health->damage(7.5));
        self::assertSame(12.5, $health->current());
        self::assertSame(12.5, $health->damage(50.0));
        self::assertSame(0.0, $health->current());
        self::assertTrue($health->isDead());
    }

    public function test_healing_never_exceeds_maximum_health(): void
    {
        $health = new Health(20.0, 12.0);

        self::assertSame(5.0, $health->heal(5.0));
        self::assertSame(17.0, $health->current());
        self::assertSame(3.0, $health->heal(10.0));
        self::assertSame(20.0, $health->current());
    }

    public function test_it_rejects_invalid_initial_health(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Health(20.0, 21.0);
    }

    public function test_it_rejects_negative_damage(): void
    {
        $health = new Health(20.0);

        $this->expectException(InvalidArgumentException::class);

        $health->damage(-1.0);
    }
}
