<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Value;

use InvalidArgumentException;
use Jogo\Domain\Value\Vector2;
use PHPUnit\Framework\TestCase;

final class Vector2Test extends TestCase
{
    public function testItAddsSubtractsAndScalesVectorsWithoutMutatingThem(): void
    {
        $original = new Vector2(3.0, 4.0);
        $other = new Vector2(1.0, -2.0);

        self::assertTrue($original->add($other)->equals(new Vector2(4.0, 2.0)));
        self::assertTrue($original->subtract($other)->equals(new Vector2(2.0, 6.0)));
        self::assertTrue($original->scale(2.0)->equals(new Vector2(6.0, 8.0)));
        self::assertTrue($original->equals(new Vector2(3.0, 4.0)));
    }

    public function testItCalculatesLengthDistanceAndNormalization(): void
    {
        $vector = new Vector2(3.0, 4.0);
        $normalized = $vector->normalized();

        self::assertSame(25.0, $vector->lengthSquared());
        self::assertSame(5.0, $vector->length());
        self::assertSame(5.0, $vector->distanceTo(Vector2::zero()));
        self::assertEqualsWithDelta(0.6, $normalized->x(), 1.0E-9);
        self::assertEqualsWithDelta(0.8, $normalized->y(), 1.0E-9);
        self::assertEqualsWithDelta(1.0, $normalized->length(), 1.0E-9);
    }

    public function testItNormalizesAZeroVectorWithoutDividingByZero(): void
    {
        self::assertTrue(Vector2::zero()->normalized()->equals(Vector2::zero()));
    }

    public function testItRejectsNonFiniteComponents(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Vector2(INF, 0.0);
    }

    public function testItRejectsANegativeComparisonTolerance(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Vector2::zero()->equals(Vector2::zero(), -1.0);
    }
}
