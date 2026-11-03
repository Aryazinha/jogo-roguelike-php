<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Results;

interface ResultsScreenRendererInterface
{
    public function drawFinish(
        FinalRunView $run,
        string $name,
        string $message,
        bool $saved,
        int $selected,
    ): void;

    public function drawRanking(
        RankingPage $page,
        int $offset,
        string $message,
    ): void;
}
