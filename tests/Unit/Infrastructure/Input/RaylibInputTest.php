<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Infrastructure\Input;

use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Input\InputInterface;
use Jogo\Infrastructure\Input\RaylibInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RaylibInputTest extends TestCase
{
    /** @param list<int> $keys */
    #[DataProvider('movementCases')]
    public function test_movement(
        array $keys,
        float $x,
        float $y
    ): void {
        $state = $this->fake($keys)->poll();

        self::assertTrue(
            $state->movement->equals(new Vector2($x, $y))
        );
    }

    /** @return iterable<string, array{list<int>, float, float}> */
    public static function movementCases(): iterable
    {
        yield 'parado' => [[], 0.0, 0.0];
        yield 'cima' => [[87], 0.0, -1.0];
        yield 'baixo' => [[83], 0.0, 1.0];
        yield 'esquerda' => [[65], -1.0, 0.0];
        yield 'direita' => [[68], 1.0, 0.0];
        yield 'diagonal' => [[87, 68], 1.0, -1.0];
        yield 'opostos horizontais' => [[65, 68], 0.0, 0.0];
        yield 'opostos verticais' => [[87, 83], 0.0, 0.0];
        yield 'todos opostos' => [[65, 68, 87, 83], 0.0, 0.0];
    }

    public function test_facing_is_independent_from_movement(): void
    {
        $state = $this->fake([87, 262])->poll();

        self::assertTrue(
            $state->movement->equals(new Vector2(0.0, -1.0))
        );

        self::assertNotNull($state->facing);

        self::assertTrue(
            $state->facing->equals(new Vector2(1.0, 0.0))
        );
    }

    public function test_diagonal_facing_is_normalized(): void
    {
        $state = $this->fake([262, 265])->poll();

        self::assertNotNull($state->facing);
        self::assertEqualsWithDelta(
            1.0,
            $state->facing->length(),
            1.0E-9
        );
    }

    public function test_opposite_arrows_cancel_facing(): void
    {
        $state = $this->fake([262, 263])->poll();

        self::assertNull($state->facing);
    }

    public function test_no_arrows_preserves_previous_facing(): void
    {
        $state = $this->fake([68])->poll();

        self::assertNull($state->facing);
    }

    public function test_pause_is_requested_only_once(): void
    {
        $input = $this->fake([], [80]);

        self::assertInstanceOf(InputInterface::class, $input);
        self::assertTrue($input->poll()->pauseRequested);
        self::assertFalse($input->poll()->pauseRequested);
    }

    public function test_holding_p_without_new_press_does_not_pause(): void
    {
        $input = $this->fake([80]);

        self::assertFalse($input->poll()->pauseRequested);
    }

    /**
     * @param  list<int>  $held
     * @param  list<int>  $pressed
     */
    private function fake(
        array $held,
        array $pressed = []
    ): RaylibInput {
        /** @var array<int, bool> $heldMap */
        $heldMap = array_fill_keys($held, true);

        /** @var array<int, bool> $pressedMap */
        $pressedMap = array_fill_keys($pressed, true);

        return new RaylibInput(
            static fn (int $key): bool => isset($heldMap[$key]),
            static function (int $key) use (&$pressedMap): bool {
                if (! isset($pressedMap[$key])) {
                    return false;
                }

                unset($pressedMap[$key]);

                return true;
            }
        );
    }
}
