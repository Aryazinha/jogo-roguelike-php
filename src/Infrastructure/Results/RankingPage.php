<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Results;

final readonly class RankingPage
{
    /** @param list<RankingRow> $rows */
    public function __construct(
        public array $rows,
        public bool $hasNext,
    ) {}
}
