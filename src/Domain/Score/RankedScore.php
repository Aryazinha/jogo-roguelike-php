<?php

declare(strict_types=1);

namespace Jogo\Domain\Score;

/** Linha do ranking (RF34): posição, resultado salvo e momento do registro. */
final readonly class RankedScore
{
    public function __construct(
        public int $position,
        public int $id,
        public ScoreEntry $entry,
        public string $createdAt,
    ) {}
}
