<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Value;

use InvalidArgumentException;
use Jogo\Domain\Value\Facing;
use Jogo\Domain\Value\Vector2;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FacingTest extends TestCase
{
    public function test_it_requires_an_explicit_initial_direction_and_preserves_it_for_null(): void
    {
        $initial = new Vector2(0.0, -1.0);
        $facing = new Facing($initial);
        self::assertSame($initial, $facing->direction());

        $facing->update(null);
        self::assertSame($initial, $facing->direction());

        $next = (new Vector2(1.0, -1.0))->normalized();
        $facing->update($next);
        $facing->update(null);
        self::assertSame($next, $facing->direction());
    }

    #[DataProvider('invalidDirections')]
    public function test_it_rejects_invalid_initial_directions(float $x, float $y): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Facing(new Vector2($x, $y));
    }

    /** @return iterable<string, array{float, float}> */
    public static function invalidDirections(): iterable
    {
        yield 'zero' => [0.0, 0.0];
        yield 'comprimento dois' => [0.0, 2.0];
        yield 'diagonal não normalizada' => [1.0, 1.0];
        yield 'infinito' => [INF, 0.0];
        yield 'menos infinito' => [0.0, -INF];
        yield 'NaN' => [NAN, 0.0];
    }

    public function test_an_invalid_update_does_not_erase_the_last_direction(): void
    {
        $initial = new Vector2(-1.0, 0.0);
        $facing = new Facing($initial);

        try {
            $facing->update(Vector2::zero());
            self::fail('Uma direção nula deveria ser rejeitada.');
        } catch (InvalidArgumentException) {
            self::assertSame($initial, $facing->direction());
        }
    }
}
