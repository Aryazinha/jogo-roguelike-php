<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\System;

use Jogo\Domain\Chest\HealingItem;
use Jogo\Domain\Random\RandomIndex;
use Jogo\Domain\Scenery\Breakable;
use Jogo\Domain\Scenery\BreakableType;
use Jogo\Domain\Scenery\DropChance;
use Jogo\Domain\Scenery\ScenerySettings;
use Jogo\Domain\Scenery\TemporaryBonus;
use Jogo\Domain\System\BreakableSystem;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\ModifierType;
use Jogo\Domain\Value\StatModifier;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\MapLayout;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

/** Caixote das fixtures: 3 de vida; sorteio 0-4499 Steak, 4500-7999 bônus, 8000-9999 item especial. */
final class BreakableSystemTest extends TestCase
{
    public function test_crate_breaks_once_after_its_health_and_drops_one_item(): void
    {
        [$world, $system] = self::setUpScenery(0);

        self::assertSame(2.0, $system->damage('crate-1', 2.0));
        self::assertCount(0, $world->pickups());
        self::assertSame(1.0, $system->damage('crate-1', 5.0));
        self::assertSame(0.0, $system->damage('crate-1', 5.0), 'já quebrado');
        self::assertSame(0.0, $system->damage('nao-existe', 5.0));

        $pickups = $world->pickups();
        self::assertCount(1, $pickups);
        self::assertSame('steak', $pickups[0]->itemId());
        self::assertSame(5.0, $pickups[0]->healing()?->heal);
        self::assertTrue($pickups[0]->position()->equals(new Vector2(300.0, 300.0)));
        $world->flush();
        self::assertSame([], $world->breakables());
    }

    public function test_bonus_drop_carries_a_configured_bonus(): void
    {
        [$world, $system] = self::setUpScenery(4500);

        $system->damage('crate-1', 10.0);

        self::assertSame('bonus_temporario', $world->pickups()[0]->itemId());
        self::assertSame('furia', $world->pickups()[0]->bonus()?->id);
    }

    public function test_nothing_drop_leaves_the_floor_empty(): void
    {
        [$world, $system] = self::setUpScenery(0, grass: true);

        $system->damage('grass-1', 1.0);

        self::assertSame([], $world->pickups());
    }

    public function test_area_hit_only_touches_breakables_inside(): void
    {
        [$world, $system] = self::setUpScenery(9000);
        $world->addBreakable(new Breakable('crate-2', self::crate(), new Vector2(600.0, 300.0)));

        self::assertSame(1, $system->damageArea(new Vector2(330.0, 300.0), 20.0, 10.0));
        self::assertSame('item_especial', $world->pickups()[0]->itemId());
        self::assertFalse($world->breakables()[1]->health()->isDead());
    }

    public function test_player_picks_up_by_touching_and_each_item_is_reported_once(): void
    {
        [$world, $system] = self::setUpScenery(0);
        $system->damage('crate-1', 10.0);

        $system->update(1.0);
        self::assertSame([], $system->takePicked(), 'longe do jogador');

        $world->player()->moveTo(new Vector2(310.0, 300.0));
        $system->update(2.0);
        $system->update(3.0);
        $picked = $system->takePicked();

        self::assertCount(1, $picked);
        self::assertSame(['pickup-1', 'steak', 5.0, 2.0], [$picked[0]->pickupId, $picked[0]->itemId,
            $picked[0]->healing?->heal, $picked[0]->pickedAtSeconds]);
        self::assertSame([], $system->takePicked());
    }

    /** @return array{GameWorld, BreakableSystem} */
    private static function setUpScenery(int $roll, bool $grass = false): array
    {
        $world = new GameWorld(new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(20.0)),
            new MapLayout(1000.0, 1000.0, 10.0, 100.0, 200.0));
        $type = $grass ? new BreakableType('grass', 'Grama', 1.0, 12.0, false, [new DropChance('nenhum', 1.0)]) : self::crate();
        $world->addBreakable(new Breakable($type->id.'-1', $type, new Vector2(300.0, 300.0)));
        $settings = new ScenerySettings(8.0, [self::crate()],
            [new TemporaryBonus('furia', 'Furia', 15.0, [new StatModifier('dano', ModifierType::PERCENT, 0.5, 'teste')])]);

        // Mesmo número em todo sorteio, mas dentro da faixa: no sorteio do bônus (1 opção) vira 0.
        $random = new class($roll) implements RandomIndex
        {
            public function __construct(private readonly int $roll) {}

            public function nextIndex(int $count): int
            {
                return $this->roll % $count;
            }
        };

        return [$world, new BreakableSystem($world, $settings, new HealingItem('steak', 'Steak', 5.0), $random)];
    }

    private static function crate(): BreakableType
    {
        return new BreakableType('crate', 'Caixote', 3.0, 18.0, true, [new DropChance('steak', 0.45),
            new DropChance('bonus_temporario', 0.35), new DropChance('item_especial', 0.2)]);
    }
}
