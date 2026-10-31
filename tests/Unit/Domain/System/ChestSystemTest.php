<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\System;

use Jogo\Core\EventBus;
use Jogo\Domain\Chest\Chest;
use Jogo\Domain\Chest\ChestRarity;
use Jogo\Domain\Chest\ChestSettings;
use Jogo\Domain\Chest\HealingItem;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\System\ChestSystem;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\MapLayout;
use Jogo\Tests\Support\ControlledRandomIndex;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

final class ChestSystemTest extends TestCase
{
    public function test_boss_always_leaves_a_chest_where_it_died(): void
    {
        [$world, $system, $bus] = self::setUpChests(9999);

        $bus->publish(self::kill('warden', true, 600.0, 700.0));
        self::assertCount(0, $world->chests(), 'só entra no próximo update');
        $system->update(642.0);

        $chests = $world->chests();
        self::assertCount(1, $chests);
        self::assertTrue($chests[0]->position()->equals(new Vector2(600.0, 700.0)));
        // Sorteio 9999 cai na última faixa: ouro.
        self::assertSame('ouro', $chests[0]->rarity()->id);
    }

    public function test_common_enemy_drops_a_chest_only_inside_the_chance(): void
    {
        [$world, $system, $bus] = self::setUpChests(39);
        $bus->publish(self::kill('zombie', false));
        $system->update(1.0);
        self::assertCount(1, $world->chests());

        [$world, $system, $bus] = self::setUpChests(40);
        $bus->publish(self::kill('zombie', false));
        $system->update(1.0);
        self::assertSame([], $world->chests());
    }

    public function test_treasure_multiplier_raises_the_chance(): void
    {
        [$world, $system, $bus] = self::setUpChests(80);

        $bus->publish(self::kill('zombie', false));
        $system->setChanceMultiplier(2.5);
        $bus->publish(self::kill('zombie', false));
        $system->update(1.0);

        self::assertCount(1, $world->chests(), 'só a segunda morte foi durante o Tesouro');
    }

    public function test_chest_opens_once_when_the_player_touches_it(): void
    {
        [$world, $system, $bus] = self::setUpChests(0);
        $bus->publish(self::kill('warden', true, 1200.0, 1000.0));
        $system->update(1.0);

        $system->update(2.0);
        self::assertSame([], $system->takeOpened(), 'longe do jogador não abre');

        $world->player()->moveTo(new Vector2(1210.0, 1000.0));
        $system->update(3.0);
        $system->update(4.0);
        $rewards = $system->takeOpened();

        self::assertCount(1, $rewards);
        self::assertSame(['chest-1', 'madeira', 'upgrade_de_arma', 1, 3.0], [$rewards[0]->chestId, $rewards[0]->rarityId,
            $rewards[0]->rewardType, $rewards[0]->quantity, $rewards[0]->openedAtSeconds]);
        self::assertSame([], $system->takeOpened(), 'cada recompensa sai uma vez');
        $world->flush();
        self::assertSame([], $world->chests());
    }

    public function test_chest_entity_returns_the_reward_only_the_first_time(): void
    {
        $chest = new Chest('chest-1', Vector2::zero(), new ChestRarity('bronze', 'Bau de Bronze', 0.1, 'upgrade_de_arma', 2));

        self::assertNotNull($chest->open(5.0));
        self::assertNull($chest->open(6.0));
        self::assertTrue($chest->isOpened());
        self::assertFalse($chest->isActive());
    }

    public function test_chests_appear_in_the_world_view_as_chest(): void
    {
        [$world, $system, $bus] = self::setUpChests(0);
        $bus->publish(self::kill('warden', true, 1200.0, 1000.0));
        $system->update(1.0);

        $kinds = array_column(array_map(static fn ($v) => ['kind' => $v->kind], $world->snapshots()), 'kind');

        self::assertSame(['player', 'chest'], $kinds);
    }

    /** @return array{GameWorld, ChestSystem, EventBus} */
    private static function setUpChests(int $roll): array
    {
        $player = new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(20.0));
        $world = new GameWorld($player, new MapLayout(2000.0, 2000.0, 32.0, 500.0, 700.0));
        $settings = new ChestSettings([
            new ChestRarity('madeira', 'Bau de Madeira', 0.85, 'upgrade_de_arma', 1),
            new ChestRarity('bronze', 'Bau de Bronze', 0.10, 'upgrade_de_arma', 2),
            new ChestRarity('ouro', 'Bau de Ouro', 0.05, 'upgrade_de_arma', 3),
        ], 1, 0.004, new HealingItem('steak', 'Steak', 5.0));
        $bus = new EventBus;
        $system = new ChestSystem($world, $settings, new ControlledRandomIndex($roll));
        $system->subscribe($bus);

        return [$world, $system, $bus];
    }

    private static function kill(string $type, bool $boss, float $x = 300.0, float $y = 300.0): EnemyKilled
    {
        return new EnemyKilled('enemy-1', $type, $boss, new Vector2($x, $y), 'player-1', null, 1.0);
    }
}
