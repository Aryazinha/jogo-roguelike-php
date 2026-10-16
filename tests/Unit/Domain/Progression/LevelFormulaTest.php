<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Progression;

use InvalidArgumentException;
use Jogo\Domain\Progression\LevelFormula;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LevelFormulaTest extends TestCase
{
    /** @return iterable<string, array{int, int}> */
    public static function levels(): iterable
    {
        yield 'nivel 0 (2n + 7)' => [0, 7];
        yield 'nivel 1 (2n + 7)' => [1, 9];
        yield 'nivel 15, fim da primeira faixa' => [15, 37];
        yield 'nivel 16, inicio da segunda faixa (5n - 38)' => [16, 42];
        yield 'nivel 30, fim da segunda faixa' => [30, 112];
        yield 'nivel 31, inicio da terceira faixa (9n - 158)' => [31, 121];
        yield 'nivel 50' => [50, 292];
    }

    #[DataProvider('levels')]
    public function test_required_xp_follows_the_three_ranges_of_rf13(int $level, int $expected): void
    {
        self::assertSame($expected, LevelFormula::requiredXp($level));
    }

    public function test_required_xp_never_decreases(): void
    {
        for ($level = 0; $level < 100; $level++) {
            self::assertGreaterThan(LevelFormula::requiredXp($level), LevelFormula::requiredXp($level + 1));
        }
    }

    public function test_negative_level_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LevelFormula::requiredXp(-1);
    }
}
