<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Value;

use InvalidArgumentException;
use Jogo\Domain\Value\MapBounds;
use Jogo\Domain\Value\Vector2;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MapBoundsTest extends TestCase
{
    public function test_it_clamps_both_axes_and_accepts_positions_at_the_edges(): void
    {
        $bounds = new MapBounds(-10.0, -20.0, 30.0, 40.0);

        self::assertTrue($bounds->clamp(new Vector2(-50.0, -50.0))->equals(new Vector2(-10.0, -20.0)));
        self::assertTrue($bounds->clamp(new Vector2(50.0, 50.0))->equals(new Vector2(30.0, 40.0)));
        self::assertTrue($bounds->clamp(new Vector2(5.0, -10.0))->equals(new Vector2(5.0, -10.0)));
        self::assertTrue($bounds->clamp(new Vector2(-10.0, 40.0))->equals(new Vector2(-10.0, 40.0)));
    }

    public function test_equal_limits_can_lock_an_axis(): void
    {
        $bounds = new MapBounds(4.0, -10.0, 4.0, 10.0);

        self::assertTrue($bounds->clamp(new Vector2(20.0, 3.0))->equals(new Vector2(4.0, 3.0)));
    }

    #[DataProvider('invalidBounds')]
    public function test_it_rejects_invalid_limits(float $minX, float $minY, float $maxX, float $maxY): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MapBounds($minX, $minY, $maxX, $maxY);
    }

    /** @return iterable<string, array{float, float, float, float}> */
    public static function invalidBounds(): iterable
    {
        yield 'x invertido' => [2.0, 0.0, 1.0, 1.0];
        yield 'y invertido' => [0.0, 2.0, 1.0, 1.0];

        foreach ([INF, -INF, NAN] as $valueIndex => $value) {
            for ($axis = 0; $axis < 4; $axis++) {
                $limits = [0.0, 0.0, 1.0, 1.0];
                $limits[$axis] = $value;
                yield "limite $axis não finito $valueIndex" => [$limits[0], $limits[1], $limits[2], $limits[3]];
            }
        }
    }
}
