<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Wave;

use InvalidArgumentException;
use Jogo\Domain\Enemy\Enemy;
use Jogo\Domain\Enemy\EnemyCatalog;
use Jogo\Domain\Enemy\EnemyFactory;
use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Wave\WaveBand;
use Jogo\Domain\Wave\WaveDirector;
use Jogo\Domain\Wave\WaveSettings;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\MapLayout;
use Jogo\Domain\World\SpawnPointPicker;
use Jogo\Tests\Support\EnemyFixtures;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

/**
 * Curva de teste: 0-10 s spider a cada 2 s (2 por vez); 10-20 s zombie/spider
 * a cada 1 s (3 por vez); depois husk a cada 0,5 s (1 por vez). Teto 10.
 */
final class WaveDirectorTest extends TestCase
{
    private const DT = 1 / 60;

    public function test_first_group_appears_exactly_at_the_interval(): void
    {
        [$world, $director] = self::setUpDirector();

        $spawnedAt = [];
        for ($step = 1; $step <= 300; $step++) {
            if ($director->update(self::DT, $step / 60, 1) > 0) {
                $spawnedAt[] = $step;
            }
        }

        self::assertSame([120, 240], $spawnedAt);
        self::assertSame(['spider'], array_values(array_unique(self::types($world))));
        self::assertCount(4, $world->enemies());
    }

    public function test_band_changes_at_its_start_second(): void
    {
        [, $director] = self::setUpDirector();

        $director->update(self::DT, 9.99, 1);
        self::assertSame(0, $director->currentBandIndex());
        $director->update(self::DT, 10.0, 1);
        self::assertSame(1, $director->currentBandIndex());
        $director->update(self::DT, 500.0, 1);
        self::assertSame(2, $director->currentBandIndex());
    }

    public function test_type_respects_the_minimum_level(): void
    {
        // Na faixa 1, o Zombie só aparece a partir do nível 6.
        [$low, $director] = self::setUpDirector(maxAlive: 300);
        self::simulate($director, 10.0, 15.0, 1);
        self::assertSame(['spider'], array_values(array_unique(self::types($low))));

        [$high, $director] = self::setUpDirector(maxAlive: 300);
        self::simulate($director, 10.0, 15.0, 6);
        self::assertEqualsCanonicalizing(['spider', 'zombie'], array_values(array_unique(self::types($high))));
    }

    public function test_falls_back_to_any_unlocked_type(): void
    {
        // Faixa 2 só tem Husk (nível 12); no nível 1 a partida não fica vazia.
        [$world, $director] = self::setUpDirector();

        self::simulate($director, 20.0, 21.0, 1);

        self::assertNotEmpty($world->enemies());
        self::assertSame(['spider'], array_values(array_unique(self::types($world))));
    }

    public function test_enemies_get_stronger_with_the_level(): void
    {
        [$world, $director] = self::setUpDirector();

        self::simulate($director, 0.0, 2.0, 11);

        // +10% de vida e +5% de dano por nível acima do 1: nível 11 = 2× vida, 1,5× dano.
        $spider = $world->enemies()[0];
        self::assertEqualsWithDelta(26.0, $spider->health()->maximum(), 1e-9);
        self::assertEqualsWithDelta(4.5, $spider->contactDamage(), 1e-9);
    }

    public function test_cap_is_never_passed_and_the_rest_waits_for_space(): void
    {
        [$world, $director] = self::setUpDirector(maxAlive: 10);

        self::simulate($director, 10.0, 20.0, 1);
        self::assertSame(10, $world->aliveEnemyCount());
        self::assertGreaterThan(0, $director->owed());

        // Três morrem: no próximo passo os devidos ocupam exatamente as três vagas.
        foreach (array_slice($world->enemies(), 0, 3) as $enemy) {
            $enemy->health()->damage(1000.0);
        }
        $world->flush();
        self::assertSame(3, $director->update(self::DT, 20.0, 1));
        self::assertSame(10, $world->aliveEnemyCount());
    }

    public function test_spawn_points_are_inside_the_map_and_off_screen(): void
    {
        [$world, $director] = self::setUpDirector(maxAlive: 300);

        self::simulate($director, 10.0, 20.0, 6);

        foreach ($world->enemies() as $enemy) {
            self::assertTrue($world->layout()->contains($enemy->position()));
            self::assertGreaterThanOrEqual(500.0 - 1e-6, $enemy->position()->distanceTo($world->player()->position()));
        }
    }

    public function test_same_seed_gives_the_same_wave(): void
    {
        $runs = [];
        foreach ([1, 2] as $run) {
            [$world, $director] = self::setUpDirector(maxAlive: 300, seed: 99);
            self::simulate($director, 0.0, 25.0, 7);
            $runs[$run] = array_map(static fn (Enemy $e) => [$e->id(), $e->typeId(), $e->position()->x(), $e->position()->y()],
                $world->enemies());
        }

        self::assertSame($runs[1], $runs[2]);
    }

    public function test_event_multipliers_change_count_and_interval(): void
    {
        [$world, $director] = self::setUpDirector(maxAlive: 300);

        // Horda: quantidade × 3 e intervalo × 0,5 → 6 spiders a cada 1 s.
        $times = [];
        for ($step = 1; $step <= 120; $step++) {
            if ($director->update(self::DT, $step / 60, 1, 3.0, 0.5) > 0) {
                $times[] = $step;
            }
        }

        self::assertSame([60, 120], $times);
        self::assertCount(12, $world->enemies());
    }

    public function test_pause_and_invalid_level(): void
    {
        [$world, $director] = self::setUpDirector();

        for ($i = 0; $i < 1000; $i++) {
            self::assertSame(0, $director->update(0.0, 5.0, 1));
        }
        self::assertSame([], $world->enemies());

        $this->expectException(InvalidArgumentException::class);
        $director->update(self::DT, 5.0, 0);
    }

    private static function simulate(WaveDirector $director, float $from, float $to, int $level): void
    {
        for ($step = (int) round($from * 60) + 1; $step <= (int) round($to * 60); $step++) {
            $director->update(self::DT, $step / 60, $level);
        }
    }

    /** @return list<string> */
    private static function types(GameWorld $world): array
    {
        return array_map(static fn (Enemy $e) => $e->typeId(), $world->enemies());
    }

    /** @return array{GameWorld, WaveDirector} */
    private static function setUpDirector(int $maxAlive = 10, int $seed = 5): array
    {
        $player = new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(20.0));
        $layout = new MapLayout(2000.0, 2000.0, 32.0, 500.0, 700.0);
        $world = new GameWorld($player, $layout);
        $catalog = new EnemyCatalog(EnemyFixtures::fullSet());
        $settings = new WaveSettings([
            new WaveBand(0.0, 10.0, 2.0, 2, ['spider']),
            new WaveBand(10.0, 20.0, 1.0, 3, ['zombie', 'spider']),
            new WaveBand(20.0, null, 0.5, 1, ['husk']),
        ], $maxAlive, 0.1, 0.05);
        $random = new SeededRandomIndex($seed);
        $director = new WaveDirector($settings, $catalog, new EnemyFactory($catalog), $world,
            new SpawnPointPicker($layout, $random), $random);

        return [$world, $director];
    }
}
