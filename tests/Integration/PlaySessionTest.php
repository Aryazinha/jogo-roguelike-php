<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Application\Play\PlaySession;
use Jogo\Core\EventBus;
use Jogo\Domain\Chest\Chest;
use Jogo\Domain\Chest\ChestRarity;
use Jogo\Domain\Chest\HealingItem;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Event\ItemPicked;
use Jogo\Domain\Scenery\Pickup;
use Jogo\Domain\Value\Damage;
use Jogo\Domain\Value\InputState;
use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Feedback\ChestOpenedNotice;
use PHPUnit\Framework\TestCase;

/** Partida jogável sem janela: armas (A), progressão (B) e conteúdo (C) no mesmo passo. */
final class PlaySessionTest extends TestCase
{
    private const DT = 1 / 60;

    public function test_weapons_kill_real_enemies_and_the_kills_become_xp(): void
    {
        $play = PlaySession::start(dirname(__DIR__, 2), 'alex', 5, new EventBus);
        $still = new InputState(Vector2::zero());

        for ($step = 0; $step < 60 * 60 && $play->progression->run->enemiesKilled() === 0; $step++) {
            $play->step(self::DT, $still);
        }

        self::assertGreaterThan(0, $play->progression->run->enemiesKilled(), 'o arco da Alex mata quem se aproxima');
        self::assertGreaterThan(0, $play->progression->experience->experience()->totalXp());
    }

    public function test_movement_input_moves_the_player_inside_the_map(): void
    {
        $play = PlaySession::start(dirname(__DIR__, 2), 'steve', 5, new EventBus);
        $start = $play->progression->player->position();

        $play->step(1.0, new InputState(new Vector2(1.0, 0.0), new Vector2(1.0, 0.0)));

        self::assertGreaterThan($start->x(), $play->progression->player->position()->x());
        self::assertSame(1.0, $play->facing->direction()->x());
    }

    public function test_floor_items_and_chests_reach_the_progression_services(): void
    {
        $events = new EventBus;
        $play = PlaySession::start(dirname(__DIR__, 2), 'steve', 5, $events);
        $picked = [];
        $events->subscribe(ItemPicked::class, static function (ItemPicked $event) use (&$picked): void {
            $picked[] = $event->itemId;
        });
        $notices = 0;
        $events->subscribe(ChestOpenedNotice::class, static function () use (&$notices): void {
            $notices++;
        });
        $player = $play->progression->player;
        $player->health()->damage(6.0);
        $here = $player->position();
        $world = $play->content->world;
        $world->addPickup(new Pickup('chao-1', $here, 'steak', new HealingItem('steak', 'Steak', 5.0)));
        $world->addPickup(new Pickup('chao-2', $here, 'xp_pequeno', xp: 5));
        $world->addPickup(new Pickup('chao-3', $here, 'item_especial', weaponUpgrades: 1));
        $world->addChest(new Chest('bau-1', $here, new ChestRarity('madeira', 'Bau de Madeira', 0.85, 'upgrade_de_arma', 1)));

        $play->step(self::DT, new InputState(Vector2::zero()));

        self::assertSame(['steak', 'xp_pequeno'], $picked);
        self::assertSame($player->health()->maximum() - 1.0, $player->health()->current(), 'o Steak cura 5 dos 6 de dano');
        self::assertSame(5, $play->progression->experience->experience()->totalXp());
        self::assertSame(3, $play->progression->weaponLevels->level('stone_sword'), 'Livro Encantado e baú: um upgrade cada');
        self::assertSame(1, $notices);
    }

    public function test_victory_waits_for_a_chest_or_the_grace_period(): void
    {
        $events = new EventBus;
        $play = PlaySession::start(dirname(__DIR__, 2), 'steve', 5, $events);
        $still = new InputState(Vector2::zero());
        $play->step(self::DT, $still);

        $events->publish(new EnemyKilled('warden-1', 'warden', true, Vector2::zero(), null, null, $play->elapsedSeconds()));
        // Sem curar, o jogador parado morreria antes: aqui só interessa o prazo da vitória.
        for ($step = 0; $step < 19 * 60; $step++) {
            $play->progression->player->health()->heal(100.0);
            $play->step(self::DT, $still);
        }
        self::assertFalse($play->isOver(), 'ainda dá tempo de abrir o baú do boss');

        for ($step = 0; $step < 2 * 60 && ! $play->isOver(); $step++) {
            $play->progression->player->health()->heal(100.0);
            $play->step(self::DT, $still);
        }
        self::assertTrue($play->isOver());
        self::assertTrue($play->progression->run->summary()->won);

        $elapsed = $play->elapsedSeconds();
        $play->step(self::DT, $still);
        self::assertSame($elapsed, $play->elapsedSeconds(), 'depois do fim nada mais anda');
    }

    public function test_opening_a_chest_after_the_boss_ends_the_match_at_once(): void
    {
        $events = new EventBus;
        $play = PlaySession::start(dirname(__DIR__, 2), 'steve', 5, $events);
        $events->publish(new EnemyKilled('warden-1', 'warden', true, Vector2::zero(), null, null, 0.0));
        $play->content->world->addChest(new Chest('bau-1', $play->progression->player->position(),
            new ChestRarity('madeira', 'Bau de Madeira', 0.85, 'upgrade_de_arma', 1)));

        $play->step(self::DT, new InputState(Vector2::zero()));

        self::assertTrue($play->isOver());
        self::assertTrue($play->progression->run->summary()->won);
    }

    public function test_dying_ends_the_match_as_a_defeat(): void
    {
        $play = PlaySession::start(dirname(__DIR__, 2), 'steve', 5, new EventBus);
        $player = $play->progression->player;

        $play->content->damage->damagePlayer($player, new Damage(100.0), 0.0);

        self::assertTrue($play->isOver());
        self::assertFalse($play->progression->run->summary()->won);
    }
}
