<?php

declare(strict_types=1);

namespace Jogo\Tools;

use Jogo\Infrastructure\Results\FinalRunView;
use Jogo\Infrastructure\Results\RankingPage;
use Jogo\Infrastructure\Results\RankingRow;
use Jogo\Infrastructure\Results\ResultsGatewayInterface;
use Jogo\Infrastructure\Results\SaveOutcome;
use PDO;
use RuntimeException;

final class DemoResultsSqlite implements ResultsGatewayInterface
{
    private PDO $pdo;

    public function __construct(string $path)
    {
        $this->pdo = new PDO('sqlite:'.$path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS demo_scores (
                run_id TEXT PRIMARY KEY,
                player_name TEXT NOT NULL,
                character_name TEXT NOT NULL,
                level INTEGER NOT NULL,
                kills INTEGER NOT NULL,
                elapsed_seconds REAL NOT NULL,
                score INTEGER NOT NULL,
                victory INTEGER NOT NULL
            )'
        );
    }

    public function save(
        FinalRunView $run,
        string $name
    ): SaveOutcome {
        $name = trim($name);

        // Regra provisoria apenas para o teste grafico.
        // A validacao definitiva sera fornecida pela Frente B.
        if ($name === '' || mb_strlen($name, 'UTF-8') > 20) {
            return new SaveOutcome(
                false,
                'Nome deve ter entre 1 e 20 caracteres.'
            );
        }

        $statement = $this->pdo->prepare(
            'INSERT OR IGNORE INTO demo_scores
            (run_id, player_name, character_name, level,
             kills, elapsed_seconds, score, victory)
             VALUES
            (:run_id, :player_name, :character_name, :level,
             :kills, :elapsed_seconds, :score, :victory)'
        );

        if ($statement === false) {
            throw new RuntimeException('Falha ao preparar salvamento.');
        }

        $statement->execute([
            ':run_id' => $run->runId,
            ':player_name' => $name,
            ':character_name' => $run->character,
            ':level' => $run->level,
            ':kills' => $run->kills,
            ':elapsed_seconds' => $run->elapsedSeconds,
            ':score' => $run->score,
            ':victory' => (int) $run->victory,
        ]);

        return new SaveOutcome(
            true,
            $statement->rowCount() > 0
                ? 'Pontuacao salva com sucesso!'
                : 'Esta partida ja estava registrada.'
        );
    }

    public function page(int $offset, int $limit): RankingPage
    {
        if ($offset < 0 || $limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException(
                'Paginacao invalida.'
            );
        }

        // Ordenacao demonstrativa equivalente ao plano da B.
        // Na integracao real, a D consumira a ordem fornecida pela B.
        $statement = $this->pdo->prepare(
            'SELECT player_name, character_name, score,
                    level, kills, elapsed_seconds
             FROM demo_scores
             ORDER BY score DESC, elapsed_seconds ASC, rowid ASC
             LIMIT :limit OFFSET :offset'
        );

        if ($statement === false) {
            throw new RuntimeException('Falha ao preparar consulta.');
        }

        $statement->bindValue(':limit', $limit + 1, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $records = $statement->fetchAll(PDO::FETCH_ASSOC);
        $hasNext = count($records) > $limit;
        $rows = [];

        foreach (array_slice($records, 0, $limit) as $record) {
            $rows[] = new RankingRow(
                (string) $record['player_name'],
                (string) $record['character_name'],
                (int) $record['score'],
                (int) $record['level'],
                (int) $record['kills'],
                (float) $record['elapsed_seconds']
            );
        }

        return new RankingPage($rows, $hasNext);
    }
}
