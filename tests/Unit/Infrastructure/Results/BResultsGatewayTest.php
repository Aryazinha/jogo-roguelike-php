<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Infrastructure\Results;

use Jogo\Domain\Run\RunSummary;
use Jogo\Infrastructure\Persistence\Migrator;
use Jogo\Infrastructure\Persistence\SqliteConnection;
use Jogo\Infrastructure\Persistence\SqliteScoreRepository;
use Jogo\Infrastructure\Results\BResultsGateway;
use Jogo\Infrastructure\Results\FinalRunView;
use PHPUnit\Framework\TestCase;

final class BResultsGatewayTest extends TestCase
{
    public function test_real_sqlite_ranking_persists_and_saves_once(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'd12-ranking-');
        self::assertIsString($file);
        $root = dirname(__DIR__, 4);

        try {
            $pdo = SqliteConnection::open($file);
            (new Migrator($pdo, $root.'/database/migrations'))->migrate();
            $gateway = new BResultsGateway(new SqliteScoreRepository($pdo));
            $run = $gateway->register(
                'run-one',
                new RunSummary('steve', 3, 4, 25.75, false, 41)
            );
            self::assertSame(390, $run->score);
            self::assertFalse($gateway->save($run, '   ')->success);
            self::assertTrue($gateway->save($run, ' Ana  Silva ')->success);
            self::assertTrue($gateway->save($run, 'Ana Silva')->success);

            $page = $gateway->page(0, 6);
            self::assertCount(1, $page->rows);
            self::assertSame('Ana Silva', $page->rows[0]->name);
            self::assertFalse($page->hasNext);

            // Abrir novo processo/conexao deve manter o ranking.
            $second = new BResultsGateway(new SqliteScoreRepository(
                SqliteConnection::open($file)
            ));
            self::assertCount(1, $second->page(0, 6)->rows);
        } finally {
            @unlink($file);
        }
    }

    public function test_unregistered_partida_is_not_saved(): void
    {
        $pdo = SqliteConnection::open(':memory:');
        (new Migrator($pdo, dirname(__DIR__, 4).'/database/migrations'))->migrate();
        $gateway = new BResultsGateway(new SqliteScoreRepository($pdo));
        $run = $gateway->register('known', new RunSummary('alex', 1, 0, 0.0, false, 1));
        $unknown = new FinalRunView(
            'unknown', $run->character, $run->level, $run->kills,
            $run->elapsedSeconds, $run->score, $run->victory
        );
        self::assertFalse($gateway->save($unknown, 'Ana')->success);
        self::assertSame([], $gateway->page(0, 6)->rows);
    }
}
