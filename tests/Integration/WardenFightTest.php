<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Core\Config\BossConfigLoader;
use Jogo\Core\Config\ContactConfigLoader;
use Jogo\Core\Config\EnemyConfigLoader;
use Jogo\Core\Config\MapConfigLoader;
use Jogo\Core\Config\SteeringConfigLoader;
use Jogo\Core\EventBus;
use Jogo\Domain\Enemy\EnemyFactory;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Domain\System\BossSystem;
use Jogo\Domain\System\DamageSystem;
use Jogo\Domain\System\EnemyAttackSystem;
use Jogo\Domain\System\EnemySteeringSystem;
use Jogo\Domain\System\MovementSystem;
use Jogo\Domain\Value\Damage;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\SpawnPointPicker;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

/**
 * Critério de aceite da C6 com o conteúdo real: a mesma seed repete as
 * fases, e a derrota do Warden é distinguível da morte de um comum.
 */
final class WardenFightTest extends TestCase
{
    public function test_full_fight_is_reproducible_and_ends_with_one_boss_kill(): void
    {
        $first = self::fight(2026);
        $second = self::fight(2026);

        self::assertSame($first['phases'], $second['phases']);
        self::assertSame($first['defeatedAt'], $second['defeatedAt']);

        // Todas as fases aparecem numa luta de verdade, inclusive a fúria.
        foreach (['perseguindo', 'telegrafando', 'investindo', 'recuperando', 'invocando'] as $phase) {
            self::assertContains($phase, $first['phases']);
        }
        self::assertTrue($first['enraged']);
        // 8 Zombies em 70% e 8 em 40%.
        self::assertSame(16, $first['summoned']);
        self::assertSame([true], $first['bossKills']);
        self::assertNotNull($first['defeatedAt']);
        self::assertGreaterThan(600.0, $first['defeatedAt']);
    }

    /** @return array{phases: list<string>, defeatedAt: ?float, enraged: bool, summoned: int, bossKills: list<bool>} */
    private static function fight(int $seed): array
    {
        $config = dirname(__DIR__, 2).'/config';
        $catalog = (new EnemyConfigLoader)->load("{$config}/enemies.json");
        $layout = (new MapConfigLoader)->load("{$config}/map.json");
        $bus = new EventBus;
        $player = new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(100_000.0));
        $world = new GameWorld($player, $layout);
        $factory = new EnemyFactory($catalog);
        $movement = new MovementSystem($layout->bounds());
        $boss = new BossSystem($world, $factory, (new BossConfigLoader($catalog))->load("{$config}/enemies.json"),
            new SpawnPointPicker($layout, new SeededRandomIndex($seed)), $movement, 'warden', 600.0);
        $boss->subscribe($bus);
        $steering = new EnemySteeringSystem($movement, ['perseguir' => (new SteeringConfigLoader)->load("{$config}/enemies.json")->chase()]);
        $damage = new DamageSystem($bus);
        $attacks = new EnemyAttackSystem($damage, (new ContactConfigLoader)->load("{$config}/enemies.json"));
        $bossKills = [];
        $summoned = 0;
        $bus->subscribe(EnemyKilled::class, static function (EnemyKilled $event) use (&$bossKills): void {
            if ($event->isBoss) {
                $bossKills[] = true;
            }
        });

        $phases = [];
        $enraged = false;
        // Começa em 599 s; uma "arma" tira 6 de vida do boss a cada 0,5 s: 900 de vida em 75 s.
        for ($step = 0; $step < 60 * 120 && ! $boss->isDefeated(); $step++) {
            $elapsed = 599.0 + $step / 60;
            $steering->update($world, 1 / 60);
            $boss->update(1 / 60, $elapsed);
            $attacks->update($world, $elapsed);
            $warden = $boss->boss();
            if ($warden !== null && $step % 30 === 0) {
                $damage->damageEnemy($warden, new Damage(6.0, 'player-1', 'test'), 'warden', true, $elapsed);
            }
            $world->flush();
            $phase = $boss->phase();
            if ($phase !== null && end($phases) !== $phase->value) {
                $phases[] = $phase->value;
            }
            $enraged = $enraged || $boss->isEnraged();
        }
        foreach ($world->enemies() as $enemy) {
            if ($enemy->typeId() === 'zombie') {
                $summoned++;
            }
        }

        return ['phases' => $phases, 'defeatedAt' => $boss->defeatedAt(), 'enraged' => $enraged,
            'summoned' => $summoned, 'bossKills' => $bossKills];
    }
}
