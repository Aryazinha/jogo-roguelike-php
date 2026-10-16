<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Application\Progression\ProgressionSession;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Event\LevelUp;
use Jogo\Domain\Event\PlayerDied;
use Jogo\Domain\Score\PlayerName;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\Weapon;
use Jogo\Infrastructure\Persistence\Migrator;
use Jogo\Infrastructure\Persistence\SqliteConnection;
use Jogo\Infrastructure\Persistence\SqliteScoreRepository;
use Jogo\Tests\Support\CombatHarness;
use PHPUnit\Framework\TestCase;

final class ProgressionSessionTest extends TestCase
{
    private string $database;

    protected function setUp(): void
    {
        $this->database = sys_get_temp_dir().'/jogo-b11-'.bin2hex(random_bytes(6)).'.sqlite';
    }

    protected function tearDown(): void
    {
        if (is_file($this->database)) {
            unlink($this->database);
        }
    }

    public function test_full_headless_run_from_first_kill_to_the_saved_ranking(): void
    {
        $result = $this->play(2026);

        self::assertGreaterThanOrEqual(9, $result['level']);
        self::assertSame(count($result['levelUps']), $result['level'] - 1);
        self::assertCount(count($result['levelUps']), $result['choices']);
        self::assertTrue($result['won']);
        self::assertSame(10, $result['kills']);
        self::assertSame(5.0, $result['steakHealed']);
        self::assertSame(['furia'], $result['expiredBonuses']);
        self::assertSame(1, count($result['chest']));
        self::assertSame(120, $result['seconds']);
        self::assertSame($result['level'] * 100 + 10 * 10 + 120 * 2 + 500, $result['score']);

        $ranking = $this->repository()->ranking();
        self::assertCount(1, $ranking);
        self::assertSame('Carol', $ranking[0]->entry->playerName);
        self::assertSame($result['score'], $ranking[0]->entry->score);
        self::assertSame('steve', $ranking[0]->entry->characterId);
        self::assertSame(2026, $ranking[0]->entry->seed);
    }

    public function test_same_seed_and_same_inputs_reproduce_the_whole_progression(): void
    {
        $first = $this->play(77);
        unlink($this->database);

        self::assertSame($first, $this->play(77));
    }

    public function test_nothing_advances_after_the_player_dies(): void
    {
        $harness = new CombatHarness;
        $session = $this->session($harness, 1);
        $session->update(10.0);
        $harness->events->publish(new PlayerDied('player', Vector2::zero(), null, 10.0));

        $session->update(10.0);

        self::assertSame(10.0, $session->run->survivalSeconds());
        self::assertTrue($session->run->isEnded());
    }

    /**
     * @return array{levelUps: list<int>, choices: list<string>, level: int, kills: int, won: bool, seconds: int,
     *     score: int, steakHealed: ?float, expiredBonuses: list<string>, chest: list<string>, weapons: list<string>,
     *     passives: array<string, int>, damage: ?float}
     */
    private function play(int $seed): array
    {
        $harness = new CombatHarness;
        $session = $this->session($harness, $seed);
        /** @var list<int> $levelUps */
        $levelUps = [];
        $harness->events->subscribe(LevelUp::class, static function (LevelUp $event) use (&$levelUps): void {
            $levelUps[] = $event->level;
        });
        $choices = [];

        for ($index = 0; $index < 8; $index++) {
            $harness->events->publish(self::killed("zombie-{$index}", 'zombie', (float) $index));
        }
        $choices = array_merge($choices, $this->resolveChoices($session));

        $session->player->health()->damage(6.0);
        $steakHealed = $session->pickups->collectHealing('steak-1', $session->steak, 20.0);
        $session->pickups->collectBonus('bonus-1', 'furia', 20.0);
        $expired = [];
        for ($step = 0; $step < 60 * 60; $step++) {
            $expired = array_merge($expired, $session->update(1.0 / 60.0));
        }
        $chest = $session->chests->grantWeaponUpgrades('bau-1', 1);

        $harness->events->publish(self::killed('warden-1', 'warden', 60.0, boss: true));
        $choices = array_merge($choices, $this->resolveChoices($session));
        $harness->events->publish(self::killed('zombie-after-boss', 'zombie', 61.0));
        $choices = array_merge($choices, $this->resolveChoices($session));
        for ($step = 0; $step < 60 * 60; $step++) {
            $session->update(1.0 / 60.0);
        }

        $session->player->health()->damage($session->player->health()->current());
        $harness->events->publish(new PlayerDied('player', Vector2::zero(), 'zombie-final', 120.0));
        $entry = $session->finish(new PlayerName('Carol'), $this->repository());
        $summary = $session->run->summary();

        return [
            'levelUps' => $levelUps,
            'choices' => $choices,
            'level' => $summary->level,
            'kills' => $summary->enemiesKilled,
            'won' => $summary->won,
            'seconds' => $summary->wholeSeconds(),
            'score' => $entry->score,
            'steakHealed' => $steakHealed,
            'expiredBonuses' => $expired,
            'chest' => $chest,
            'weapons' => array_map(static fn (Weapon $weapon): string => $weapon->definition()->id, $session->player->inventory()->weapons()),
            'passives' => $session->passives->owned(),
            'damage' => $session->player->inventory()->at(0)?->definition()->damage,
        ];
    }

    /** @return list<string> */
    private function resolveChoices(ProgressionSession $session): array
    {
        $chosen = [];
        while ($session->levelUps->hasPendingChoice()) {
            $offer = $session->levelUps->currentOffer();
            if ($offer === []) {
                continue;
            }
            $option = $offer[0];
            $session->levelUps->choose(0, $option->requiresReplacement ? 1 : null);
            $chosen[] = $option->key();
        }

        return $chosen;
    }

    private function session(CombatHarness $harness, int $seed): ProgressionSession
    {
        return ProgressionSession::start(dirname(__DIR__, 2).'/config', 'steve', 'player', Vector2::zero(), 6.0, $seed,
            $harness->events, $harness->combat, $harness->combat);
    }

    private function repository(): SqliteScoreRepository
    {
        $pdo = SqliteConnection::open($this->database);
        (new Migrator($pdo, dirname(__DIR__, 2).'/database/migrations'))->migrate();

        return new SqliteScoreRepository($pdo);
    }

    private static function killed(string $id, string $type, float $elapsed, bool $boss = false): EnemyKilled
    {
        return new EnemyKilled($id, $type, $boss, Vector2::zero(), 'player', 'stone_sword', $elapsed);
    }
}
