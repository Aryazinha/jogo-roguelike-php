<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Infrastructure\Input;

use InvalidArgumentException;
use Jogo\Domain\Value\InputState;
use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Input\InputInterface;
use Jogo\Infrastructure\Input\ScriptedInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ScriptedInputTest extends TestCase
{
    public function test_it_returns_states_in_order_and_then_neutral_input_without_repeating_pause(): void
    {
        $first = new InputState(new Vector2(1.0, 0.0));
        $second = new InputState(Vector2::zero(), new Vector2(0.0, 1.0), true);
        $input = new ScriptedInput([$first, $second]);

        self::assertInstanceOf(InputInterface::class, $input);
        self::assertSame($first, $input->poll());
        self::assertSame($second, $input->poll());

        for ($poll = 0; $poll < 3; $poll++) {
            $neutral = $input->poll();
            self::assertTrue($neutral->movement->equals(Vector2::zero()));
            self::assertNull($neutral->facing);
            self::assertFalse($neutral->pauseRequested);
        }
    }

    public function test_an_empty_script_is_neutral(): void
    {
        self::assertTrue((new ScriptedInput([]))->poll()->movement->equals(Vector2::zero()));
    }

    /** @param array<array-key, mixed> $states */
    #[DataProvider('invalidScripts')]
    public function test_it_rejects_invalid_scripts(array $states): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ScriptedInput($states);
    }

    /** @return iterable<string, array{array<array-key, mixed>}> */
    public static function invalidScripts(): iterable
    {
        yield 'índices não sequenciais' => [[1 => new InputState(Vector2::zero())]];
        yield 'valor inválido' => [[null]];
    }
}
