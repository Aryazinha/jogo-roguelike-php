<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Core\Config\ChestConfigLoader;
use Jogo\Core\Config\MapConfigLoader;
use Jogo\Core\Config\SceneryConfigLoader;
use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Domain\Scenery\SceneryPlacer;
use Jogo\Domain\System\BreakableSystem;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

/** Critério de aceite da C10 com scenery.json, chests.json e map.json reais. */
final class SceneryDropsTest extends TestCase
{
    public function test_real_scenery_is_placed_broken_and_collected(): void
    {
        $config = dirname(__DIR__, 2).'/config';
        $settings = (new SceneryConfigLoader)->load("{$config}/scenery.json");
        $player = new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(20.0));
        $world = new GameWorld($player, (new MapConfigLoader)->load("{$config}/map.json"));
        $random = new SeededRandomIndex(21);

        $placed = (new SceneryPlacer($settings, $random))->place($world);
        // 8 por bloco de 1000 × 1000 px num mapa útil de 3936 × 3936 px.
        self::assertSame(124, $placed);

        $system = new BreakableSystem($world, $settings, (new ChestConfigLoader)->load("{$config}/chests.json")->healing, $random);
        $items = [];
        foreach ($world->breakables() as $breakable) {
            $system->damage($breakable->id(), 1000.0);
            $system->damage($breakable->id(), 1000.0);
        }
        foreach ($world->pickups() as $pickup) {
            $items[$pickup->itemId()] = ($items[$pickup->itemId()] ?? 0) + 1;
        }
        $world->flush();

        self::assertSame([], $world->breakables());
        // No máximo um item por elemento, e só itens que existem nas tabelas.
        self::assertLessThanOrEqual(124, array_sum($items));
        self::assertSame([], array_diff(array_keys($items), ['steak', 'xp_pequeno', 'item_especial', 'bonus_temporario']));

        // O jogador anda até o primeiro item e o pega.
        $first = $world->pickups()[0];
        $player->moveTo($first->position());
        $system->update(30.0);
        $picked = $system->takePicked();
        self::assertSame($first->id(), $picked[0]->pickupId);
    }
}
