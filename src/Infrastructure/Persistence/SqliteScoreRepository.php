<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Persistence;

use InvalidArgumentException;
use Jogo\Domain\Score\RankedScore;
use Jogo\Domain\Score\ScoreEntry;
use Jogo\Domain\Score\ScoreRepositoryInterface;
use PDO;

/** Ranking em SQLite (RNF01), sobre a tabela `scores` da migração 001. */
final readonly class SqliteScoreRepository implements ScoreRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function save(ScoreEntry $entry): int
    {
        $insert = $this->pdo->prepare('INSERT INTO scores
            (nome_jogador, personagem, pontuacao, nivel, inimigos_derrotados, tempo_segundos, venceu, semente)
            VALUES (:nome, :personagem, :pontuacao, :nivel, :inimigos, :tempo, :venceu, :semente)');
        $insert->execute([
            'nome' => $entry->playerName,
            'personagem' => $entry->characterId,
            'pontuacao' => $entry->score,
            'nivel' => $entry->level,
            'inimigos' => $entry->enemiesKilled,
            'tempo' => $entry->survivalSeconds,
            'venceu' => $entry->won ? 1 : 0,
            'semente' => $entry->seed,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function ranking(int $limit = 10, ?string $characterId = null): array
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('O limite do ranking deve ser >= 1.');
        }
        $where = $characterId === null ? '' : 'WHERE personagem = :personagem';
        $query = $this->pdo->prepare("SELECT * FROM scores {$where}
            ORDER BY pontuacao DESC, tempo_segundos ASC, criado_em ASC, id ASC LIMIT :limite");
        if ($characterId !== null) {
            $query->bindValue('personagem', $characterId);
        }
        $query->bindValue('limite', $limit, PDO::PARAM_INT);
        $query->execute();
        $ranking = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $index => $row) {
            $ranking[] = new RankedScore($index + 1, self::int($row, 'id'), new ScoreEntry(
                self::string($row, 'nome_jogador'), self::string($row, 'personagem'), self::int($row, 'pontuacao'),
                self::int($row, 'nivel'), self::int($row, 'inimigos_derrotados'), self::int($row, 'tempo_segundos'),
                self::int($row, 'venceu') === 1, self::int($row, 'semente'),
            ), self::string($row, 'criado_em'));
        }

        return $ranking;
    }

    /** @param array<array-key, mixed> $row */
    private static function int(array $row, string $column): int
    {
        $value = $row[$column] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }
        throw new InvalidArgumentException("scores.{$column} contém um valor inválido.");
    }

    /** @param array<array-key, mixed> $row */
    private static function string(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (! is_string($value)) {
            throw new InvalidArgumentException("scores.{$column} contém um valor inválido.");
        }

        return $value;
    }
}
