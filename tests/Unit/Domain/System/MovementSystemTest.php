<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\System;

use InvalidArgumentException;
use Jogo\Domain\Entity\Entity;
use Jogo\Domain\Entity\Movable;
use Jogo\Domain\System\MovementSystem;
use Jogo\Domain\Value\Facing;
use Jogo\Domain\Value\InputState;
use Jogo\Domain\Value\MapBounds;
use Jogo\Domain\Value\Vector2;
use Jogo\Tests\Support\FakeMovable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MovementSystemTest extends TestCase
{
    #[DataProvider('axisMovements')]
    public function test_horizontal_and_vertical_movement_use_speed_times_dt(float $x, float $y): void
    {
        $target = new FakeMovable(new Vector2(10.0, 20.0));
        $facing = new Facing(new Vector2(0.0, -1.0));

        $this->newSystem()->update($target, $facing, new InputState(new Vector2($x, $y)), 80.0, 0.25, true);

        self::assertTrue($target->position()->equals(new Vector2(10.0 + 20.0 * $x, 20.0 + 20.0 * $y)));
        self::assertTrue($facing->direction()->equals(new Vector2(0.0, -1.0)));
    }

    /** @return iterable<string, array{float, float}> */
    public static function axisMovements(): iterable
    {
        yield 'direita' => [1.0, 0.0];
        yield 'esquerda' => [-1.0, 0.0];
        yield 'baixo' => [0.0, 1.0];
        yield 'cima' => [0.0, -1.0];
    }

    #[DataProvider('diagonalMovements')]
    public function test_diagonal_movement_has_the_same_maximum_speed(float $x, float $y): void
    {
        $target = new FakeMovable(Vector2::zero());
        $facing = new Facing(new Vector2(1.0, 0.0));

        $this->newSystem()->update($target, $facing, new InputState(new Vector2($x, $y)), 80.0, 0.25, true);

        self::assertEqualsWithDelta(20.0, $target->position()->length(), 1.0E-9);
        self::assertEqualsWithDelta(20.0 * $x / sqrt(2.0), $target->position()->x(), 1.0E-9);
        self::assertEqualsWithDelta(20.0 * $y / sqrt(2.0), $target->position()->y(), 1.0E-9);
    }

    /** @return iterable<string, array{float, float}> */
    public static function diagonalMovements(): iterable
    {
        yield 'direita baixo' => [1.0, 1.0];
        yield 'direita cima' => [1.0, -1.0];
        yield 'esquerda baixo' => [-1.0, 1.0];
        yield 'esquerda cima' => [-1.0, -1.0];
    }

    public function test_partial_analog_input_keeps_its_magnitude(): void
    {
        $target = new FakeMovable(Vector2::zero());
        $facing = new Facing(new Vector2(1.0, 0.0));
        $this->newSystem()->update($target, $facing, new InputState(new Vector2(0.3, 0.4)), 80.0, 0.25, true);

        self::assertTrue($target->position()->equals(new Vector2(6.0, 8.0)));
        self::assertEqualsWithDelta(10.0, $target->position()->length(), 1.0E-9);
    }

    public function test_zero_input_keeps_position_and_null_facing_keeps_the_last_look(): void
    {
        $position = new Vector2(3.0, 4.0);
        $initialFacing = new Vector2(0.0, -1.0);
        $target = new FakeMovable($position);
        $facing = new Facing($initialFacing);
        $system = $this->newSystem();
        $system->update($target, $facing, new InputState(Vector2::zero()), 80.0, 1.0, true);

        self::assertSame($position, $target->position());
        self::assertSame($initialFacing, $facing->direction());

        $nextFacing = new Vector2(-1.0, 0.0);
        $system->update($target, $facing, new InputState(Vector2::zero(), $nextFacing), 80.0, 1.0, true);
        self::assertSame($position, $target->position());
        self::assertSame($nextFacing, $facing->direction());

        $system->update($target, $facing, new InputState(new Vector2(1.0, 0.0)), 80.0, 1.0, true);
        self::assertSame($nextFacing, $facing->direction());
    }

    public function test_explicit_facing_is_independent_of_the_movement_axis(): void
    {
        $target = new FakeMovable(Vector2::zero());
        $facing = new Facing(new Vector2(1.0, 0.0));
        $look = new Vector2(0.0, -1.0);

        $this->newSystem()->update($target, $facing, new InputState(new Vector2(1.0, 0.0), $look), 60.0, 0.5, true);

        self::assertTrue($target->position()->equals(new Vector2(30.0, 0.0)));
        self::assertSame($look, $facing->direction());
    }

    public function test_zero_speed_or_zero_dt_do_not_move(): void
    {
        $target = new FakeMovable(new Vector2(3.0, 4.0));
        $position = $target->position();
        $facing = new Facing(new Vector2(1.0, 0.0));
        $input = new InputState(new Vector2(1.0, 1.0));
        $system = $this->newSystem();

        $system->update($target, $facing, $input, 0.0, 0.5, true);
        $system->update($target, $facing, $input, 60.0, 0.0, true);
        self::assertSame($position, $target->position());
    }

    public function test_external_speed_can_change_between_updates(): void
    {
        $target = new FakeMovable(Vector2::zero());
        $facing = new Facing(new Vector2(1.0, 0.0));
        $system = $this->newSystem();
        $input = new InputState(new Vector2(1.0, 0.0));
        $system->update($target, $facing, $input, 20.0, 0.5, true);
        $system->update($target, $facing, $input, 40.0, 0.5, true);

        self::assertTrue($target->position()->equals(new Vector2(30.0, 0.0)));
    }

    public function test_external_map_limits_clamp_both_axes_and_allow_movement_along_the_edge(): void
    {
        $target = new FakeMovable(Vector2::zero());
        $facing = new Facing(new Vector2(1.0, 0.0));
        $system = new MovementSystem(new MapBounds(-10.0, -20.0, 30.0, 40.0));

        $system->update($target, $facing, new InputState(new Vector2(1.0, 1.0)), 200.0, 1.0, true);
        self::assertTrue($target->position()->equals(new Vector2(30.0, 40.0)));
        $system->update($target, $facing, new InputState(new Vector2(1.0, -1.0)), 20.0, 1.0, true);
        self::assertSame(30.0, $target->position()->x());
        self::assertEqualsWithDelta(40.0 - 20.0 / sqrt(2.0), $target->position()->y(), 1.0E-9);
        $system->update($target, $facing, new InputState(new Vector2(-1.0, -1.0)), 200.0, 1.0, true);
        self::assertTrue($target->position()->equals(new Vector2(-10.0, -20.0)));
    }

    public function test_a_deactivated_entity_does_not_move_or_change_facing(): void
    {
        $target = new class('test', new Vector2(3.0, 4.0), 0.0) extends Entity {};
        $position = $target->position();
        $facing = new Facing(new Vector2(1.0, 0.0));
        $look = $facing->direction();
        $target->deactivate();
        self::assertInstanceOf(Movable::class, $target);

        $this->newSystem()->update(
            $target, $facing, new InputState(new Vector2(1.0, 1.0), new Vector2(0.0, -1.0)),
            60.0, 1.0, $target->isActive(),
        );

        self::assertSame($position, $target->position());
        self::assertSame($look, $facing->direction());
    }

    #[DataProvider('invalidNumbers')]
    public function test_it_rejects_invalid_speed_and_dt(float $speed, float $dt): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->newSystem()->update(
            new FakeMovable(Vector2::zero()), new Facing(new Vector2(1.0, 0.0)),
            new InputState(new Vector2(1.0, 0.0)), $speed, $dt, true,
        );
    }

    /** @return iterable<string, array{float, float}> */
    public static function invalidNumbers(): iterable
    {
        foreach ([-1.0, INF, -INF, NAN] as $index => $value) {
            yield "velocidade inválida $index" => [$value, 1.0];
            yield "dt inválido $index" => [60.0, $value];
        }
    }

    public function test_overflow_is_rejected_before_mutating_state(): void
    {
        $target = new FakeMovable(Vector2::zero());
        $position = $target->position();
        $facing = new Facing(new Vector2(1.0, 0.0));
        $look = $facing->direction();

        try {
            $this->newSystem()->update(
                $target, $facing, new InputState(new Vector2(1.0, 0.0), new Vector2(0.0, 1.0)),
                PHP_FLOAT_MAX, 2.0, true,
            );
            self::fail('Overflow deveria ser rejeitado.');
        } catch (InvalidArgumentException) {
            self::assertSame($position, $target->position());
            self::assertSame($look, $facing->direction());
        }
    }

    private function newSystem(): MovementSystem
    {
        // Valores de cenário de teste, sem estabelecer velocidade ou tamanho oficiais.
        return new MovementSystem(new MapBounds(-1000.0, -1000.0, 1000.0, 1000.0));
    }
}
