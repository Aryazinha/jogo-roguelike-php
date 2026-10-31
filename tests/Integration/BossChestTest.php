<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Core\Config\ChestConfigLoader;
use Jogo\Core\Config\EnemyConfigLoader;
use Jogo\Core\EventBus;
use Jogo\Domain\Enemy\EnemyFactory;
use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Domain\System\ChestSystem;
use Jogo\Domain\System\DamageSystem;
use Jogo\Domain\Value\Damage;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\MapLayout;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

/** Critério de aceite da C8: derrotar o Warden real gera um baú que dá exatamente uma recompensa. */
final class BossChestTest extends TestCase
{
    public function test_warden_kill_leaves_one_chest_with_one_traceable_reward(): void
    {
        $config = dirname(__DIR__, 2).'/config';
        $bus = new EventBus;
        $world = new GameWorld(new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(20.0)),
            new MapLayout(4000.0, 4000.0, 32.0, 760.0, 960.0));
        $chests = new ChestSystem($world, (new ChestConfigLoader)->load("{$config}/chests.json"), new SeededRandomIndex(4));
        $chests->subscribe($bus);
        $warden = (new EnemyFactory((new EnemyConfigLoader)->load("{$config}/enemies.json")))
            ->create('warden', new Vector2(2300.0, 2000.0));
        $world->addEnemy($warden);

        // Golpes além da morte não criam mais baús: EnemyKilled sai uma vez só.
        $damage = new DamageSystem($bus);
        foreach ([500.0, 500.0, 500.0] as $amount) {
            $damage->damageEnemy($warden, new Damage($amount, 'player-1', 'stone_sword'), 'warden', true, 650.0);
        }
        $world->flush();
        $chests->update(650.0);
        self::assertCount(1, $world->chests());

        // O jogador anda até o baú.
        $world->player()->moveTo(new Vector2(2290.0, 2000.0));
        $chests->update(655.0);
        $rewards = $chests->takeOpened();

        self::assertCount(1, $rewards);
        self::assertSame('chest-1', $rewards[0]->chestId);
        self::assertContains($rewards[0]->rarityId, ['madeira', 'bronze', 'ouro']);
        self::assertSame('upgrade_de_arma', $rewards[0]->rewardType);
        $chests->update(656.0);
        self::assertSame([], $chests->takeOpened());
    }
}
