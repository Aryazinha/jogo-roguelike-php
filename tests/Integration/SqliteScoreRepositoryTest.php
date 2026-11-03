<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use InvalidArgumentException;
use Jogo\Domain\Score\RankedScore;
use Jogo\Domain\Score\ScoreEntry;
use Jogo\Infrastructure\Persistence\Migrator;
use Jogo\Infrastructure\Persistence\SqliteConnection;
use Jogo\Infrastructure\Persistence\SqliteScoreRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class SqliteScoreRepositoryTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir().'/jogo-ranking-'.bin2hex(random_bytes(6)).'.sqlite';
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function test_migrations_are_applied_in_order_only_once(): void
    {
        $pdo = SqliteConnection::open($this->file);

        self::assertSame(['000_create_schema_migrations', '001_create_scores'], self::migrator($pdo)->migrate());
        self::assertSame([], self::migrator($pdo)->migrate());
        $count = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations');
        $count->execute();
        self::assertSame(2, (int) $count->fetchColumn());
    }

    public function test_ranking_orders_by_score_then_shorter_time_then_older_run(): void
    {
        $repository = $this->repository();
        $repository->save(self::entry('Lento', 1000, 300));
        $repository->save(self::entry('Campeao', 2000, 500));
        $repository->save(self::entry('Rapido', 1000, 200));
        $repository->save(self::entry('Empate antigo', 900, 100));
        $repository->save(self::entry('Empate novo', 900, 100));

        self::assertSame(['Campeao', 'Rapido', 'Lento', 'Empate antigo', 'Empate novo'], self::names($repository->ranking()));
        self::assertSame([1, 2, 3, 4, 5], array_map(static fn (RankedScore $row): int => $row->position, $repository->ranking()));
    }

    public function test_ranking_survives_closing_and_reopening_the_database(): void
    {
        $repository = $this->repository();
        $id = $repository->save(self::entry('Carol', 3100, 300, won: true));
        unset($repository);

        $reopened = $this->repository();
        $ranking = $reopened->ranking();

        self::assertCount(1, $ranking);
        self::assertSame($id, $ranking[0]->id);
        self::assertEquals(self::entry('Carol', 3100, 300, won: true), $ranking[0]->entry);
        self::assertNotSame('', $ranking[0]->createdAt);
    }

    public function test_ranking_can_be_limited_and_filtered_by_character(): void
    {
        $repository = $this->repository();
        $repository->save(self::entry('A', 500, 10, 'steve'));
        $repository->save(self::entry('B', 400, 10, 'alex'));
        $repository->save(self::entry('C', 300, 10, 'alex'));

        self::assertSame(['A', 'B'], self::names($repository->ranking(2)));
        self::assertSame(['B', 'C'], self::names($repository->ranking(10, 'alex')));
    }

    public function test_invalid_limit_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->repository()->ranking(0);
    }

    private function repository(): SqliteScoreRepository
    {
        $pdo = SqliteConnection::open($this->file);
        self::migrator($pdo)->migrate();

        return new SqliteScoreRepository($pdo);
    }

    private static function migrator(PDO $pdo): Migrator
    {
        return new Migrator($pdo, dirname(__DIR__, 2).'/database/migrations');
    }

    private static function entry(string $name, int $score, int $seconds, string $character = 'steve', bool $won = false): ScoreEntry
    {
        return new ScoreEntry($name, $character, $score, 5, 10, $seconds, $won, 7);
    }

    /**
     * @param  list<RankedScore>  $ranking
     * @return list<string>
     */
    private static function names(array $ranking): array
    {
        return array_map(static fn (RankedScore $row): string => $row->entry->playerName, $ranking);
    }
}
