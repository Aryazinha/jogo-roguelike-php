<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Results;

interface ResultsGatewayInterface
{
    /**
     * A Frente B valida/normaliza o nome e salva a pontuacao.
     *
     * @phpstan-impure
     */
    public function save(
        FinalRunView $run,
        string $name
    ): SaveOutcome;

    /**
     * A ordenacao e responsabilidade da fonte de dados.
     *
     * @phpstan-impure
     */
    public function page(int $offset, int $limit): RankingPage;
}
