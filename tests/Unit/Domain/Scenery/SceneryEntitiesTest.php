<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Scenery;

use InvalidArgumentException;
use Jogo\Domain\Chest\HealingItem;
use Jogo\Domain\Scenery\Breakable;
use Jogo\Domain\Scenery\BreakableType;
use Jogo\Domain\Scenery\DropChance;
use Jogo\Domain\Scenery\Pickup;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\MapLayout;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

final class SceneryEntitiesTest extends TestCase
{
    public function test_breakable_takes_its_values_from_the_type(): void
    {
        $crate = new Breakable('crate-1', self::crate(), new Vector2(10.0, 20.0));

        self::assertSame(3.0, $crate->health()->maximum());
        self::assertSame(18.0, $crate->collisionRadius());
        self::assertTrue($crate->blocksMovement());
        self::assertSame('crate', $crate->type()->id);
    }

    public function test_pickup_carries_only_what_its_item_needs(): void
    {
        $steak = new Pickup('pickup-1', Vector2::zero(), 'steak', new HealingItem('steak', 'Steak', 5.0));
        self::assertSame(5.0, $steak->healing()?->heal);

        foreach ([['nenhum', null], ['steak', null], ['xp_pequeno', new HealingItem('steak', 'Steak', 5.0)]] as [$item, $healing]) {
            try {
                new Pickup('pickup-2', Vector2::zero(), $item, $healing);
                self::fail("{$item} deveria ser recusado.");
            } catch (InvalidArgumentException) {
            }
        }
    }

    public function test_world_lists_scenery_and_cleans_broken_and_picked(): void
    {
        $world = new GameWorld(new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(20.0)),
            new MapLayout(1000.0, 1000.0, 10.0, 100.0, 200.0));
        $crate = new Breakable('crate-1', self::crate(), new Vector2(100.0, 100.0));
        $ore = new Breakable('ore-1', self::crate(), new Vector2(200.0, 100.0));
        $pickup = new Pickup('pickup-1', new Vector2(300.0, 100.0), 'xp_pequeno');
        $world->addBreakable($crate);
        $world->addBreakable($ore);
        $world->addPickup($pickup);

        self::assertSame([['player-1', 'player', true], ['crate-1', 'breakable', true], ['ore-1', 'breakable', true],
            ['pickup-1', 'pickup', null]], array_map(static fn ($v) => [$v->id, $v->kind, $v->isAlive], $world->snapshots()));
        self::assertSame($crate, $world->breakable('crate-1'));

        $crate->health()->damage(10.0);
        $pickup->deactivate();
        $world->flush();

        self::assertSame([$ore], $world->breakables());
        self::assertSame([], $world->pickups());
    }

    public function test_scenery_ids_share_the_world_namespace(): void
    {
        $world = new GameWorld(new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(20.0)),
            new MapLayout(1000.0, 1000.0, 10.0, 100.0, 200.0));
        $world->addBreakable(new Breakable('x-1', self::crate(), Vector2::zero()));

        $this->expectExceptionMessage('ID já usado no mundo: x-1');

        $world->addPickup(new Pickup('x-1', Vector2::zero(), 'item_especial'));
    }

    private static function crate(): BreakableType
    {
        return new BreakableType('crate', 'Caixote', 3.0, 18.0, true, [new DropChance('steak', 0.45),
            new DropChance('bonus_temporario', 0.35), new DropChance('item_especial', 0.2)]);
    }
}
