<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Value;

use InvalidArgumentException;
use Jogo\Domain\Value\InputState;
use Jogo\Domain\Value\Vector2;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class InputStateTest extends TestCase
{
    public function test_it_exposes_immutable_input_with_the_ratified_defaults(): void
    {
        $movement = new Vector2(-1.0, 1.0);
        $state = new InputState($movement);

        self::assertTrue((new ReflectionClass(InputState::class))->isReadOnly());
        self::assertSame($movement, $state->movement);
        self::assertNull($state->facing);
        self::assertFalse($state->pauseRequested);

        $facing = (new Vector2(1.0, 1.0))->normalized();
        $state = new InputState(Vector2::zero(), $facing, true);
        self::assertSame($facing, $state->facing);
        self::assertTrue($state->pauseRequested);
    }

    #[DataProvider('invalidAxes')]
    public function test_it_rejects_invalid_axes(float $x, float $y): void
    {
        $this->expectException(InvalidArgumentException::class);

        new InputState(new Vector2($x, $y));
    }

    /** @return iterable<string, array{float, float}> */
    public static function invalidAxes(): iterable
    {
        yield 'x abaixo' => [-1.01, 0.0];
        yield 'x acima' => [1.01, 0.0];
        yield 'y abaixo' => [0.0, -1.01];
        yield 'y acima' => [0.0, 1.01];

        foreach ([INF, -INF, NAN] as $index => $value) {
            yield "x não finito $index" => [$value, 0.0];
            yield "y não finito $index" => [0.0, $value];
        }
    }

    #[DataProvider('invalidFacing')]
    public function test_it_rejects_invalid_facing(float $x, float $y): void
    {
        $this->expectException(InvalidArgumentException::class);

        new InputState(Vector2::zero(), new Vector2($x, $y));
    }

    /** @return iterable<string, array{float, float}> */
    public static function invalidFacing(): iterable
    {
        yield 'zero' => [0.0, 0.0];
        yield 'não unitário' => [1.0, 1.0];
        yield 'muito grande' => [1.0E308, 1.0E308];
        yield 'infinito' => [INF, 0.0];
        yield 'menos infinito' => [0.0, -INF];
        yield 'NaN' => [NAN, 1.0];
    }
}
