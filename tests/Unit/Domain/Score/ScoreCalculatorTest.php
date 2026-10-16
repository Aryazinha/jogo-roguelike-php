<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Score;

use InvalidArgumentException;
use Jogo\Domain\Run\RunSummary;
use Jogo\Domain\Score\PlayerName;
use Jogo\Domain\Score\ScoreCalculator;
use Jogo\Domain\Score\ScoreEntry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ScoreCalculatorTest extends TestCase
{
    /** @return iterable<string, array{RunSummary, int}> */
    public static function runs(): iterable
    {
        yield 'derrota no inicio' => [new RunSummary('steve', 1, 0, 0.0, false, 1), 100];
        yield 'derrota media' => [new RunSummary('alex', 8, 120, 300.0, false, 1), 800 + 1200 + 600];
        yield 'vitoria' => [new RunSummary('steve', 20, 900, 660.0, true, 1), 2000 + 9000 + 1320 + 500];
        yield 'fracao de segundo descartada' => [new RunSummary('alex', 2, 3, 59.99, false, 1), 200 + 30 + 118];
    }

    #[DataProvider('runs')]
    public function test_score_follows_the_team_formula(RunSummary $summary, int $expected): void
    {
        self::assertSame($expected, (new ScoreCalculator)->calculate($summary));
    }

    public function test_victory_adds_exactly_500_points(): void
    {
        $calculator = new ScoreCalculator;

        self::assertSame(500, $calculator->calculate(new RunSummary('steve', 5, 10, 100.0, true, 1))
            - $calculator->calculate(new RunSummary('steve', 5, 10, 100.0, false, 1)));
    }

    public function test_same_summary_always_gives_the_same_score(): void
    {
        $summary = new RunSummary('alex', 13, 211, 487.3, false, 99);

        self::assertSame((new ScoreCalculator)->calculate($summary), (new ScoreCalculator)->calculate($summary));
    }

    public function test_invalid_summary_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RunSummary('steve', 1, -1, 0.0, false, 1);
    }

    public function test_entry_associates_the_name_with_the_run(): void
    {
        $entry = ScoreEntry::fromSummary(new RunSummary('alex', 8, 120, 300.9, true, 42), new PlayerName('  Carol  '),
            new ScoreCalculator);

        self::assertSame(['Carol', 'alex', 3100, 8, 120, 300, true, 42], [$entry->playerName, $entry->characterId,
            $entry->score, $entry->level, $entry->enemiesKilled, $entry->survivalSeconds, $entry->won, $entry->seed]);
    }
}
