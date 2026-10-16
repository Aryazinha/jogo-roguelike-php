<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Core\Config\ContactConfigLoader;
use Jogo\Core\Config\EnemyConfigLoader;
use Jogo\Core\Config\SteeringConfigLoader;
use Jogo\Core\EventBus;
use Jogo\Domain\Enemy\EnemyFactory;
use Jogo\Domain\Event\PlayerDamaged;
use Jogo\Domain\Event\PlayerDied;
use Jogo\Domain\System\DamageSystem;
use Jogo\Domain\System\EnemyAttackSystem;
use Jogo\Domain\System\EnemySteeringSystem;
use Jogo\Domain\System\MovementSystem;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\MapLayout;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

/** Fluxo IA → movimento → contato → dano → morte com o conteúdo real. */
final class EnemyChaseAndHitTest extends TestCase
{
    public function test_zombies_walk_up_hit_respecting_invulnerability_and_kill_once(): void
    {
        $config = dirname(__DIR__, 2).'/config/enemies.json';
        $bus = new EventBus;
        $hits = [];
        $deaths = 0;
        $bus->subscribe(PlayerDamaged::class, static function (PlayerDamaged $event) use (&$hits): void {
            $hits[] = $event->elapsedSeconds;
        });
        $bus->subscribe(PlayerDied::class, static function () use (&$deaths): void {
            $deaths++;
        });
        // Vida 20 como o Steve do RF16; o jogador fica parado no centro.
        $player = new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(20.0));
        $world = new GameWorld($player, new MapLayout(4000.0, 4000.0, 32.0, 760.0, 960.0));
        $factory = new EnemyFactory((new EnemyConfigLoader)->load($config));
        foreach ([[1800.0, 2000.0], [2200.0, 2000.0], [2000.0, 1800.0]] as [$x, $y]) {
            $world->addEnemy($factory->create('zombie', new Vector2($x, $y)));
        }
        $steering = new EnemySteeringSystem(new MovementSystem($world->layout()->bounds()),
            ['perseguir' => (new SteeringConfigLoader)->load($config)->chase()]);
        $attacks = new EnemyAttackSystem(new DamageSystem($bus), (new ContactConfigLoader)->load($config));

        $elapsed = 0.0;
        for ($step = 1; $step <= 60 * 10; $step++) {
            $elapsed = $step / 60;
            $steering->update($world, 1 / 60);
            $attacks->update($world, $elapsed);
        }

        // 200 px a 55 px/s: o primeiro toque acontece perto de 3,1 s, nunca antes.
        self::assertGreaterThan(3.0, $hits[0]);
        self::assertLessThan(3.5, $hits[0]);
        foreach (array_slice($hits, 1) as $index => $time) {
            self::assertGreaterThanOrEqual(0.5 - 1e-9, $time - $hits[$index], 'golpes respeitam a invulnerabilidade');
        }
        // 20 de vida e 3 por golpe: 7 golpes até morrer, e a morte é publicada uma vez.
        self::assertCount(7, $hits);
        self::assertSame(1, $deaths);
        self::assertFalse($player->isActive());
    }
}
