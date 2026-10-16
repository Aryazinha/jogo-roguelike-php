<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Core\Config\EnemyConfigLoader;
use Jogo\Core\Config\SteeringConfigLoader;
use Jogo\Domain\Ai\Behavior\SteeringSettings;
use Jogo\Domain\Enemy\Enemy;
use Jogo\Domain\Enemy\EnemyFactory;
use Jogo\Domain\System\EnemySteeringSystem;
use Jogo\Domain\System\MovementSystem;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\MapLayout;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

/**
 * Critério de aceite da C3, com os inimigos e pesos reais de enemies.json:
 * 50 inimigos que começam amontoados não terminam todos no mesmo ponto.
 */
final class EnemyCrowdTest extends TestCase
{
    private const STEP = 1 / 60;

    public function test_fifty_chasers_spread_out_instead_of_collapsing(): void
    {
        [$world, $system] = self::setUpCrowd(50, 'zombie');

        self::simulate($world, $system, 3.0);

        // PLANO.md 4.3: depois de 3 s, a menor distância entre 50 inimigos passa de 12 px.
        self::assertGreaterThan(12.0, self::minimumDistance($world->enemies()));
    }

    public function test_without_separation_the_same_crowd_overlaps(): void
    {
        // Controle do teste acima: mostra que é a separação que espalha o grupo.
        [$world, $system] = self::setUpCrowd(50, 'zombie', separationWeight: 0.0);

        self::simulate($world, $system, 3.0);

        self::assertLessThan(12.0, self::minimumDistance($world->enemies()));
    }

    public function test_every_common_enemy_gets_closer_to_the_player(): void
    {
        foreach (['zombie', 'spider', 'husk'] as $type) {
            [$world, $system] = self::setUpCrowd(10, $type);
            $player = $world->player()->position();
            $before = self::averageDistance($world->enemies(), $player);

            self::simulate($world, $system, 2.0);

            $after = self::averageDistance($world->enemies(), $player);
            $speed = $world->enemies()[0]->speed();
            // Anda pelo menos metade do que a velocidade permitiria, mesmo empurrando os vizinhos.
            self::assertLessThan($before - $speed, $after, $type);
        }
    }

    public function test_same_start_gives_the_same_positions(): void
    {
        $runs = [];
        for ($i = 0; $i < 2; $i++) {
            [$world, $system] = self::setUpCrowd(30, 'spider');
            self::simulate($world, $system, 1.0);
            $runs[] = array_map(static fn (Enemy $e) => [$e->position()->x(), $e->position()->y()], $world->enemies());
        }

        self::assertSame($runs[0], $runs[1]);
    }

    /** @return array{GameWorld, EnemySteeringSystem} */
    private static function setUpCrowd(int $count, string $type, ?float $separationWeight = null): array
    {
        $config = dirname(__DIR__, 2).'/config/enemies.json';
        $settings = (new SteeringConfigLoader)->load($config);
        if ($separationWeight !== null) {
            $settings = new SteeringSettings($settings->separationRadius,
                $settings->seekWeight, $separationWeight);
        }
        $factory = new EnemyFactory((new EnemyConfigLoader)->load($config));
        $player = new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(20.0));
        $world = new GameWorld($player, new MapLayout(4000.0, 4000.0, 32.0, 760.0, 960.0));

        // Amontoados numa malha de 4 px, 600 px à esquerda do jogador.
        for ($i = 0; $i < $count; $i++) {
            $world->addEnemy($factory->create($type, new Vector2(1400.0 + 4.0 * ($i % 7), 2000.0 + 4.0 * intdiv($i, 7))));
        }
        $system = new EnemySteeringSystem(new MovementSystem($world->layout()->bounds()), ['perseguir' => $settings->chase()]);

        return [$world, $system];
    }

    private static function simulate(GameWorld $world, EnemySteeringSystem $system, float $seconds): void
    {
        for ($step = 0; $step < (int) round($seconds / self::STEP); $step++) {
            $system->update($world, self::STEP);
        }
    }

    /** @param list<Enemy> $enemies */
    private static function minimumDistance(array $enemies): float
    {
        $minimum = INF;
        foreach ($enemies as $i => $a) {
            foreach (array_slice($enemies, $i + 1) as $b) {
                $minimum = min($minimum, $a->position()->distanceTo($b->position()));
            }
        }

        return $minimum;
    }

    /** @param list<Enemy> $enemies */
    private static function averageDistance(array $enemies, Vector2 $point): float
    {
        return array_sum(array_map(static fn (Enemy $e) => $e->position()->distanceTo($point), $enemies)) / count($enemies);
    }
}
