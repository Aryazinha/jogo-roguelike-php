<?php

declare(strict_types=1);

namespace Jogo\Domain\Score;

use Jogo\Domain\Run\RunSummary;

/** RF32, fórmula da equipe (PLANO.md §1.3): nível × 100 + mortes × 10 + segundos × 2 + vitória × 500. */
final class ScoreCalculator
{
    public const VERSION = 1;

    public function calculate(RunSummary $summary): int
    {
        return $summary->level * 100
            + $summary->enemiesKilled * 10
            + $summary->wholeSeconds() * 2
            + ($summary->won ? 500 : 0);
    }
}
