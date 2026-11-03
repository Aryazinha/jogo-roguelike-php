<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\World;

use InvalidArgumentException;
use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\MapLayout;
use Jogo\Domain\World\SpawnPointPicker;
use Jogo\Tests\Support\ControlledRandomIndex;
use PHPUnit\Framework\TestCase;

final class SpawnPointPickerTest extends TestCase
{
    public function test_points_stay_inside_the_ring_and_the_map(): void
    {
        $layout = self::layout();
        $picker = new SpawnPointPicker($layout, new SeededRandomIndex(42));
        $player = $layout->playerStart();

        for ($i = 0; $i < 500; $i++) {
            $point = $picker->pick($player);
            $distance = $point->distanceTo($player);

            self::assertTrue($layout->contains($point));
            self::assertGreaterThanOrEqual(760.0 - 1e-9, $distance);
            self::assertLessThanOrEqual(960.0 + 1e-9, $distance);
        }
    }

    public function test_same_seed_gives_the_same_points(): void
    {
        $layout = self::layout();
        $first = new SpawnPointPicker($layout, new SeededRandomIndex(7));
        $second = new SpawnPointPicker($layout, new SeededRandomIndex(7));

        for ($i = 0; $i < 20; $i++) {
            self::assertTrue($first->pick($layout->playerStart())->equals($second->pick($layout->playerStart())));
        }
    }

    public function test_points_spread_around_the_player(): void
    {
        $layout = self::layout();
        $picker = new SpawnPointPicker($layout, new SeededRandomIndex(3));
        $player = $layout->playerStart();
        $quadrants = [];

        for ($i = 0; $i < 200; $i++) {
            $offset = $picker->pick($player)->subtract($player);
            $quadrants[($offset->x() >= 0 ? 'L' : 'O').($offset->y() >= 0 ? 'S' : 'N')] = true;
        }

        self::assertCount(4, $quadrants);
    }

    public function test_player_in_a_corner_falls_back_to_the_far_corner(): void
    {
        $layout = self::layout();
        // Ângulo 180° com o jogador no canto esquerdo sempre sai do mapa.
        $random = new ControlledRandomIndex(180);
        $picker = new SpawnPointPicker($layout, $random, attempts: 3);

        $point = $picker->pick(new Vector2(40.0, 40.0));

        self::assertTrue($point->equals(new Vector2(3968.0, 3968.0)));
        self::assertSame([360, 201, 360, 201, 360, 201], $random->counts);
    }

    public function test_attempts_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SpawnPointPicker(self::layout(), new SeededRandomIndex(1), attempts: 0);
    }

    private static function layout(): MapLayout
    {
        return new MapLayout(4000.0, 4000.0, 32.0, 760.0, 960.0);
    }
}
