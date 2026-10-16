<?php

declare(strict_types=1);

namespace Jogo\Domain\Score;

/**
 * Persistência do ranking (RNF01). Ordem: pontuação decrescente, menor tempo e, persistindo
 * o empate, a partida mais antiga (PLANO.md §1.3).
 */
interface ScoreRepositoryInterface
{
    /** Salva o resultado e devolve o seu ID. */
    public function save(ScoreEntry $entry): int;

    /** @return list<RankedScore> */
    public function ranking(int $limit = 10, ?string $characterId = null): array;
}
