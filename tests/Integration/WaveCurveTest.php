<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Core\Config\EnemyConfigLoader;
use Jogo\Core\Config\MapConfigLoader;
use Jogo\Core\Config\WaveConfigLoader;
use Jogo\Domain\Enemy\EnemyFactory;
use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Wave\WaveDirector;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\SpawnPointPicker;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

/**
 * Critério de aceite da C7 com waves.json, enemies.json e map.json reais:
 * a população segue a curva, nunca passa de 300 e se repete com a mesma seed.
 */
final class WaveCurveTest extends TestCase
{
    private const DT = 1 / 20;

    public function test_without_kills_the_population_follows_the_curve_and_stops_at_the_cap(): void
    {
        $run = self::play(seed: 7, killEverySeconds: null);

        // 0-120 s: 2 Spiders a cada 2 s = 59 grupos até 118 s. Em 120 s exatos já vale a
        // faixa seguinte (o início da faixa conta), e o grupo sai com 3.
        self::assertSame(118, $run['aliveAt'][119]);
        self::assertSame(121, $run['aliveAt'][120]);
        // 120-300 s: 3 a cada 1,5 s; o teto chega antes do fim da segunda faixa.
        self::assertSame(300, $run['aliveAt'][300]);
        self::assertSame(300, $run['peak']);
        self::assertSame(['spider'], $run['typesByBand'][0]);
    }

    public function test_with_kills_each_band_brings_its_own_enemies(): void
    {
        // Matar tudo a cada 5 s simula as armas e deixa as faixas seguintes aparecerem.
        $run = self::play(seed: 7, killEverySeconds: 5);

        self::assertLessThanOrEqual(300, $run['peak']);
        self::assertSame(['spider'], $run['typesByBand'][0]);
        self::assertSame(['spider', 'zombie'], $run['typesByBand'][1]);
        self::assertSame(['skeleton', 'slime', 'zombie'], $run['typesByBand'][2]);
        self::assertSame(['husk', 'skeleton', 'slime', 'zombie'], $run['typesByBand'][3]);
    }

    public function test_same_seed_repeats_the_whole_curve(): void
    {
        self::assertSame(self::play(seed: 31, killEverySeconds: 5)['log'], self::play(seed: 31, killEverySeconds: 5)['log']);
        self::assertNotSame(self::play(seed: 31, killEverySeconds: 5)['log'], self::play(seed: 32, killEverySeconds: 5)['log']);
    }

    /**
     * Jogador sobe um nível a cada 30 s, como uma partida razoável: nível 21 aos 600 s.
     *
     * @return array{aliveAt: array<int, int>, peak: int, typesByBand: array<int, list<string>>, log: list<string>}
     */
    private static function play(int $seed, ?int $killEverySeconds): array
    {
        $config = dirname(__DIR__, 2).'/config';
        $catalog = (new EnemyConfigLoader)->load("{$config}/enemies.json");
        $layout = (new MapConfigLoader)->load("{$config}/map.json");
        $settings = (new WaveConfigLoader($catalog))->load("{$config}/waves.json");
        $world = new GameWorld(new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(20.0)), $layout);
        $random = new SeededRandomIndex($seed);
        $director = new WaveDirector($settings, $catalog, new EnemyFactory($catalog), $world,
            new SpawnPointPicker($layout, $random), $random);

        $aliveAt = [];
        $peak = 0;
        $types = [];
        $log = [];
        $seen = [];
        for ($step = 1; $step <= 600 * 20; $step++) {
            $elapsed = $step / 20;
            $director->update(self::DT, $elapsed, 1 + intdiv($step, 600));
            foreach ($world->enemies() as $enemy) {
                if (! isset($seen[$enemy->id()])) {
                    $seen[$enemy->id()] = true;
                    $types[$director->currentBandIndex()][$enemy->typeId()] = true;
                    $log[] = $enemy->id().':'.$enemy->typeId().':'.round($enemy->position()->x(), 3);
                }
            }
            $peak = max($peak, $world->aliveEnemyCount());
            if ($step % 20 === 0) {
                $aliveAt[$step / 20] = $world->aliveEnemyCount();
            }
            if ($killEverySeconds !== null && $step % ($killEverySeconds * 20) === 0) {
                foreach ($world->enemies() as $enemy) {
                    $enemy->health()->damage(1_000_000.0);
                }
                $world->flush();
            }
        }

        $typesByBand = [];
        foreach ($types as $band => $set) {
            $names = array_keys($set);
            sort($names);
            $typesByBand[$band] = $names;
        }

        return ['aliveAt' => $aliveAt, 'peak' => $peak, 'typesByBand' => $typesByBand, 'log' => $log];
    }
}
