<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Core\Config\ContactConfigLoader;
use Jogo\Core\Config\EnemyConfigLoader;
use Jogo\Core\EventBus;
use Jogo\Domain\Ai\Behavior\KeepDistanceBehavior;
use Jogo\Domain\Ai\Behavior\RangedMode;
use Jogo\Domain\Ai\Behavior\SeekBehavior;
use Jogo\Domain\Enemy\EnemyCatalog;
use Jogo\Domain\Enemy\EnemyFactory;
use Jogo\Domain\Enemy\SplitOnDeath;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Event\PlayerDamaged;
use Jogo\Domain\System\DamageSystem;
use Jogo\Domain\System\EnemyAttackSystem;
use Jogo\Domain\System\EnemyProjectileSystem;
use Jogo\Domain\System\EnemySteeringSystem;
use Jogo\Domain\System\MovementSystem;
use Jogo\Domain\System\RangedAttackSystem;
use Jogo\Domain\Value\Damage;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\MapLayout;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

/** Critério de aceite da C5: os dois especiais se comportam diferente, não só têm números diferentes. */
final class SpecialEnemiesTest extends TestCase
{
    public function test_real_skeleton_approaches_holds_its_range_and_shoots(): void
    {
        $catalog = self::catalog();
        $ranged = $catalog->get('skeleton')->ranged;
        self::assertNotNull($ranged);
        [$world, $bus] = self::world();
        $sources = [];
        $bus->subscribe(PlayerDamaged::class, static function (PlayerDamaged $event) use (&$sources): void {
            $sources[] = (string) $event->sourceEntityId;
        });
        $skeleton = (new EnemyFactory($catalog))->create('skeleton', new Vector2(2600.0, 2000.0));
        $world->addEnemy($skeleton);
        $keep = new KeepDistanceBehavior($ranged);
        $steering = new EnemySteeringSystem(new MovementSystem($world->layout()->bounds()),
            ['perseguir' => new SeekBehavior, 'skeleton' => $keep]);
        $shooter = new RangedAttackSystem;
        $arrows = new EnemyProjectileSystem(new EnemyAttackSystem(new DamageSystem($bus), self::invulnerability()));

        $modes = [];
        $closest = INF;
        for ($step = 1; $step <= 60 * 20; $step++) {
            $steering->update($world, 1 / 60);
            $shooter->update($world, 1 / 60);
            $arrows->update($world, 1 / 60, $step / 60);
            $world->flush();
            $mode = $keep->mode($skeleton->id());
            $modes[$mode === null ? 'nenhum' : $mode->value] = true;
            if ($step > 60 * 12) {
                $closest = min($closest, $skeleton->position()->distanceTo($world->player()->position()));
            }
        }

        self::assertArrayHasKey(RangedMode::CHASE->value, $modes);
        self::assertArrayHasKey(RangedMode::ORBIT->value, $modes);
        // Depois de chegar, não encosta: fica além da distância mínima da faixa.
        self::assertGreaterThanOrEqual($ranged->minDistance() - 1.0, $closest);
        self::assertNotEmpty($sources);
        foreach ($sources as $source) {
            self::assertStringStartsWith('arrow-', $source, 'o dano vem das flechas, não de contato');
        }
    }

    public function test_real_slime_splits_once_into_two_small_slimes(): void
    {
        [$world, $bus] = self::world();
        $factory = new EnemyFactory(self::catalog());
        (new SplitOnDeath($world, $factory))->subscribe($bus);
        $killed = [];
        $bus->subscribe(EnemyKilled::class, static function (EnemyKilled $event) use (&$killed): void {
            $killed[] = $event->enemyTypeId;
        });
        $damage = new DamageSystem($bus);
        $world->addEnemy($factory->create('slime', new Vector2(2300.0, 2000.0)));

        for ($round = 0; $round < 3; $round++) {
            foreach ($world->enemies() as $enemy) {
                $damage->damageEnemy($enemy, new Damage(100.0), $enemy->typeId(), $enemy->isBoss(), 1.0);
            }
            $world->flush();
        }

        self::assertSame(['slime', 'slime_pequeno', 'slime_pequeno'], $killed);
        self::assertSame([], $world->enemies());
    }

    private static function catalog(): EnemyCatalog
    {
        return (new EnemyConfigLoader)->load(dirname(__DIR__, 2).'/config/enemies.json');
    }

    private static function invulnerability(): float
    {
        return (new ContactConfigLoader)->load(dirname(__DIR__, 2).'/config/enemies.json');
    }

    /** @return array{GameWorld, EventBus} */
    private static function world(): array
    {
        $player = new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(1000.0));

        return [new GameWorld($player, new MapLayout(4000.0, 4000.0, 32.0, 760.0, 960.0)), new EventBus];
    }
}
