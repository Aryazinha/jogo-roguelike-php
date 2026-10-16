<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Persistence;

use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Aplica `database/migrations/*.sql` em ordem de nome, cada arquivo numa transação, e registra
 * a versão em `schema_migrations`. Rodar de novo não reaplica o que já foi aplicado.
 */
final readonly class Migrator
{
    public const SCHEMA_FILE = '000_create_schema_migrations.sql';

    public function __construct(private PDO $pdo, private string $directory) {}

    /** @return list<string> Migrações aplicadas nesta chamada. */
    public function migrate(): array
    {
        $files = glob($this->directory.'/*.sql');
        if ($files === false || $files === []) {
            throw new InvalidArgumentException("{$this->directory}: nenhuma migração encontrada.");
        }
        sort($files, SORT_STRING);
        if (basename($files[0]) !== self::SCHEMA_FILE) {
            throw new InvalidArgumentException('A primeira migração deve ser '.self::SCHEMA_FILE.'.');
        }
        $this->pdo->exec($this->read($files[0]));
        $applied = [];
        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if ($this->isApplied($version)) {
                continue;
            }
            $this->pdo->beginTransaction();
            try {
                $this->pdo->exec($this->read($file));
                $insert = $this->pdo->prepare('INSERT INTO schema_migrations (versao) VALUES (:versao)');
                $insert->execute(['versao' => $version]);
                $this->pdo->commit();
            } catch (Throwable $error) {
                $this->pdo->rollBack();
                throw $error;
            }
            $applied[] = $version;
        }

        return $applied;
    }

    private function isApplied(string $version): bool
    {
        $query = $this->pdo->prepare('SELECT 1 FROM schema_migrations WHERE versao = :versao');
        $query->execute(['versao' => $version]);

        return $query->fetchColumn() !== false;
    }

    private function read(string $file): string
    {
        $sql = file_get_contents($file);
        if ($sql === false || trim($sql) === '') {
            throw new InvalidArgumentException("{$file}: migração vazia ou ilegível.");
        }

        return $sql;
    }
}
