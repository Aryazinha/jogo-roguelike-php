<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Ai\Behavior;

use InvalidArgumentException;
use Jogo\Domain\Ai\Behavior\BehaviorStrategy;
use Jogo\Domain\Ai\Behavior\WeightedBehavior;
use Jogo\Domain\Ai\EntityView;
use Jogo\Domain\Ai\WorldView;
use Jogo\Domain\Value\Vector2;
use Jogo\Tests\Support\FixedWorldView;
use PHPUnit\Framework\TestCase;

final class WeightedBehaviorTest extends TestCase
{
    public function test_combines_with_weights_and_caps_the_length(): void
    {
        $behavior = new WeightedBehavior([[self::fixed(1.0, 0.0), 1.0], [self::fixed(0.0, 1.0), 2.0]]);

        $direction = $behavior->direction(FixedWorldView::enemy('enemy-1', 0.0, 0.0), self::world(), 1 / 60);

        self::assertTrue($direction->equals((new Vector2(1.0, 2.0))->normalized()));
    }

    public function test_small_sum_is_not_stretched(): void
    {
        $behavior = new WeightedBehavior([[self::fixed(0.2, 0.0), 1.0], [self::fixed(0.0, 0.1), 1.0]]);

        $direction = $behavior->direction(FixedWorldView::enemy('enemy-1', 0.0, 0.0), self::world(), 1 / 60);

        self::assertTrue($direction->equals(new Vector2(0.2, 0.1)));
    }

    public function test_opposite_wishes_cancel_out(): void
    {
        $behavior = new WeightedBehavior([[self::fixed(1.0, 0.0), 1.0], [self::fixed(-1.0, 0.0), 1.0]]);

        self::assertTrue($behavior->direction(FixedWorldView::enemy('enemy-1', 0.0, 0.0), self::world(), 1 / 60)
            ->equals(Vector2::zero()));
    }

    public function test_zero_weight_skips_the_behavior(): void
    {
        $skipped = new class implements BehaviorStrategy
        {
            public int $calls = 0;

            public function direction(EntityView $enemy, WorldView $world, float $deltaSeconds): Vector2
            {
                $this->calls++;

                return Vector2::zero();
            }
        };

        (new WeightedBehavior([[$skipped, 0.0], [self::fixed(1.0, 0.0), 1.0]]))
            ->direction(FixedWorldView::enemy('enemy-1', 0.0, 0.0), self::world(), 1 / 60);

        self::assertSame(0, $skipped->calls);
    }

    public function test_rejects_empty_list_and_negative_weight(): void
    {
        try {
            new WeightedBehavior([]);
            self::fail('Lista vazia deveria falhar.');
        } catch (InvalidArgumentException) {
        }

        $this->expectException(InvalidArgumentException::class);
        new WeightedBehavior([[self::fixed(1.0, 0.0), -1.0]]);
    }

    private static function fixed(float $x, float $y): BehaviorStrategy
    {
        return new class(new Vector2($x, $y)) implements BehaviorStrategy
        {
            public function __construct(private readonly Vector2 $value) {}

            public function direction(EntityView $enemy, WorldView $world, float $deltaSeconds): Vector2
            {
                return $this->value;
            }
        };
    }

    private static function world(): FixedWorldView
    {
        return new FixedWorldView(Vector2::zero());
    }
}
