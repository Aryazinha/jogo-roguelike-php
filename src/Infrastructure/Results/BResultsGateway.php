<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Results;

use InvalidArgumentException;
use Jogo\Domain\Run\RunSummary;
use Jogo\Domain\Score\PlayerName;
use Jogo\Domain\Score\ScoreCalculator;
use Jogo\Domain\Score\ScoreEntry;
use Jogo\Domain\Score\ScoreRepositoryInterface;

/** Liga a tela D10 ao ranking real da Frente B. */
final class BResultsGateway implements ResultsGatewayInterface
{
    /** @var array<string, RunSummary> */
    private array $runs = [];

    /** @var array<string, true> */
    private array $saved = [];

    public function __construct(private readonly ScoreRepositoryInterface $repository) {}

    public function register(string $runId, RunSummary $summary): FinalRunView
    {
        if (trim($runId) === '') {
            throw new InvalidArgumentException('ID de partida vazio.');
        }

        $this->runs[$runId] = $summary;

        return new FinalRunView(
            $runId,
            $summary->characterId,
            $summary->level,
            $summary->enemiesKilled,
            $summary->survivalSeconds,
            (new ScoreCalculator)->calculate($summary),
            $summary->won,
        );
    }

    public function save(FinalRunView $run, string $name): SaveOutcome
    {
        $summary = $this->runs[$run->runId] ?? null;

        if ($summary === null) {
            return new SaveOutcome(false, 'Partida nao registrada.');
        }

        if (isset($this->saved[$run->runId])) {
            return new SaveOutcome(true, 'Partida ja salva.');
        }

        try {
            $playerName = new PlayerName($name);
        } catch (InvalidArgumentException $error) {
            return new SaveOutcome(false, $error->getMessage());
        }

        $entry = ScoreEntry::fromSummary(
            $summary,
            $playerName,
            new ScoreCalculator
        );

        // Erros de SQLite sobem ate FinishState, que permite nova tentativa.
        $this->repository->save($entry);
        $this->saved[$run->runId] = true;

        return new SaveOutcome(true, 'Pontuacao salva com sucesso!');
    }

    public function page(int $offset, int $limit): RankingPage
    {
        if ($offset < 0 || $limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('Paginacao invalida.');
        }

        // A interface da B disponibiliza limite sem offset.
        // Preservamos a ordem oficial e fatiamos somente para a tela.
        $scores = $this->repository->ranking($offset + $limit + 1);
        $visible = array_slice($scores, $offset, $limit);
        $rows = [];

        foreach ($visible as $ranked) {
            $entry = $ranked->entry;
            $rows[] = new RankingRow(
                $entry->playerName,
                $entry->characterId,
                $entry->score,
                $entry->level,
                $entry->enemiesKilled,
                (float) $entry->survivalSeconds,
            );
        }

        return new RankingPage($rows, count($scores) > $offset + $limit);
    }
}
