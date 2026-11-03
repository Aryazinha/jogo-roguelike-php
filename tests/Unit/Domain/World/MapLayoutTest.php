<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\World;

use InvalidArgumentException;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\MapLayout;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MapLayoutTest extends TestCase
{
    public function test_bounds_respect_the_border_margin(): void
    {
        $bounds = (new MapLayout(4000.0, 3000.0, 32.0, 760.0, 960.0))->bounds();

        self::assertSame([32.0, 32.0, 3968.0, 2968.0], [$bounds->minX, $bounds->minY, $bounds->maxX, $bounds->maxY]);
    }

    public function test_player_starts_in_the_center(): void
    {
        $start = (new MapLayout(4000.0, 3000.0, 32.0, 760.0, 960.0))->playerStart();

        self::assertTrue($start->equals(new Vector2(2000.0, 1500.0)));
    }

    public function test_contains_includes_the_edge_and_excludes_the_margin(): void
    {
        $layout = new MapLayout(1000.0, 1000.0, 20.0, 100.0, 200.0);

        self::assertTrue($layout->contains(new Vector2(500.0, 500.0)));
        self::assertTrue($layout->contains(new Vector2(20.0, 980.0)));
        self::assertFalse($layout->contains(new Vector2(19.0, 500.0)));
        self::assertFalse($layout->contains(new Vector2(500.0, 1000.0)));
    }

    /** @return iterable<string, array{float, float, float, float, float}> */
    public static function invalidLayouts(): iterable
    {
        yield 'zero width' => [0.0, 1000.0, 10.0, 100.0, 200.0];
        yield 'negative margin' => [1000.0, 1000.0, -1.0, 100.0, 200.0];
        yield 'margin eats the map' => [1000.0, 1000.0, 500.0, 100.0, 200.0];
        yield 'max below min' => [1000.0, 1000.0, 10.0, 300.0, 200.0];
        yield 'spawn farther than the map' => [1000.0, 1000.0, 10.0, 800.0, 900.0];
    }

    #[DataProvider('invalidLayouts')]
    public function test_invalid_layout_is_rejected(float $width, float $height, float $margin, float $min, float $max): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MapLayout($width, $height, $margin, $min, $max);
    }
}
