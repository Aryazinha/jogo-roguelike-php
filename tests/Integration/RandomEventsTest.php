<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Core\Config\ChestConfigLoader;
use Jogo\Core\Config\EnemyConfigLoader;
use Jogo\Core\Config\EventConfigLoader;
use Jogo\Core\Config\MapConfigLoader;
use Jogo\Core\Config\WaveConfigLoader;
use Jogo\Domain\Enemy\EnemyFactory;
use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Domain\RandomEvent\EventDirector;
use Jogo\Domain\System\ChestSystem;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Wave\WaveDirector;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\SpawnPointPicker;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

/** Critério de aceite da C9: cada evento real muda só o seu alvo e acaba sem resto. */
final class RandomEventsTest extends TestCase
{
    public function test_horde_multiplies_the_spawn_rate_only_while_it_lasts(): void
    {
        $config = dirname(__DIR__, 2).'/config';
        $catalog = (new EnemyConfigLoader)->load("{$config}/enemies.json");
        $layout = (new MapConfigLoader)->load("{$config}/map.json");
        $world = new GameWorld(new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(20.0)), $layout);
        $random = new SeededRandomIndex(8);
        $waves = new WaveDirector((new WaveConfigLoader($catalog))->load("{$config}/waves.json"), $catalog,
            new EnemyFactory($catalog), $world, new SpawnPointPicker($layout, $random), $random);
        $events = new EventDirector((new EventConfigLoader)->load("{$config}/events.json"), new SeededRandomIndex(10));
        $chests = new ChestSystem($world, (new ChestConfigLoader)->load("{$config}/chests.json"), $random);

        $spawned = ['horda' => 0, 'fora' => 0];
        $seconds = ['horda' => 0, 'fora' => 0];
        $seen = [];
        // Dentro da primeira faixa (0-120 s) o ritmo base é fixo: 2 Spiders a cada 2 s.
        for ($step = 1; $step <= 120 * 20; $step++) {
            $elapsed = $step / 20;
            $events->update($elapsed);
            $chests->setChanceMultiplier($events->chestChanceMultiplier());
            $born = $waves->update(1 / 20, $elapsed, 1, $events->spawnCountMultiplier(), $events->spawnIntervalMultiplier());
            $key = $events->active()?->id === 'horda' ? 'horda' : 'fora';
            $spawned[$key] += $born;
            $seconds[$key]++;
            $seen[$events->active()->id ?? 'nenhum'] = [$events->spawnCountMultiplier(), $events->spawnIntervalMultiplier(),
                $events->xpMultiplier(), $events->chestChanceMultiplier()];
            // Mata todos a cada passo: o teto nunca interfere na medida.
            foreach ($world->enemies() as $enemy) {
                $enemy->health()->damage(1_000_000.0);
            }
            $world->flush();
        }

        self::assertArrayHasKey('horda', $seen, 'com a seed 10 a primeira Horda vai de 91 a 111 s');
        self::assertSame([3.0, 0.5, 1.0, 1.0], $seen['horda']);
        self::assertSame([1.0, 1.0, 1.0, 1.0], $seen['nenhum']);
        // Base: 1 inimigo por segundo. Na Horda: 6 por segundo (× 3 e intervalo pela metade).
        self::assertEqualsWithDelta(1.0, $spawned['fora'] / ($seconds['fora'] / 20), 0.1);
        self::assertEqualsWithDelta(6.0, $spawned['horda'] / ($seconds['horda'] / 20), 0.6);
    }
}
