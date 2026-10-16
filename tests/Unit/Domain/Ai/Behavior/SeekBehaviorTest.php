<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Ai\Behavior;

use Jogo\Domain\Ai\Behavior\SeekBehavior;
use Jogo\Domain\Value\Vector2;
use Jogo\Tests\Support\FixedWorldView;
use PHPUnit\Framework\TestCase;

final class SeekBehaviorTest extends TestCase
{
    public function test_points_to_the_player_with_unit_length(): void
    {
        $world = new FixedWorldView(new Vector2(300.0, 400.0));

        $direction = (new SeekBehavior)->direction(FixedWorldView::enemy('enemy-1', 0.0, 0.0), $world, 1 / 60);

        self::assertTrue($direction->equals(new Vector2(0.6, 0.8)));
    }

    public function test_is_zero_on_top_of_the_player(): void
    {
        $world = new FixedWorldView(new Vector2(50.0, 50.0));

        $direction = (new SeekBehavior)->direction(FixedWorldView::enemy('enemy-1', 50.0, 50.0), $world, 1 / 60);

        self::assertTrue($direction->equals(Vector2::zero()));
    }

    public function test_does_not_depend_on_the_step_size(): void
    {
        $world = new FixedWorldView(new Vector2(-10.0, 0.0));
        $enemy = FixedWorldView::enemy('enemy-1', 0.0, 0.0);

        self::assertTrue((new SeekBehavior)->direction($enemy, $world, 0.0)->equals(new Vector2(-1.0, 0.0)));
        self::assertTrue((new SeekBehavior)->direction($enemy, $world, 1.0)->equals(new Vector2(-1.0, 0.0)));
    }
}
