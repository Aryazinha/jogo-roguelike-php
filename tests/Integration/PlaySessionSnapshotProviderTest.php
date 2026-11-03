<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Application\Play\PlaySession;
use Jogo\Application\Play\PlaySessionSnapshotProvider;
use Jogo\Core\Config\EnemyConfigLoader;
use Jogo\Core\EventBus;
use Jogo\Domain\Chest\Chest;
use Jogo\Domain\Chest\ChestRarity;
use Jogo\Domain\Enemy\EnemyFactory;
use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Play\PlayEntityView;
use PHPUnit\Framework\TestCase;

final class PlaySessionSnapshotProviderTest extends TestCase
{
    public function test_frame_shows_nearby_content_and_skips_what_is_off_screen(): void
    {
        $root = dirname(__DIR__, 2);
        $play = PlaySession::start($root, 'steve', 5, new EventBus);
        $here = $play->progression->player->position();
        $factory = new EnemyFactory((new EnemyConfigLoader)->load("{$root}/config/enemies.json"), 'teste');
        $world = $play->content->world;
        $world->addEnemy($factory->create('zombie', $here->add(new Vector2(100.0, 0.0))));
        $world->addEnemy($factory->create('slime', $here->add(new Vector2(-100.0, 0.0))));
        $world->addEnemy($factory->create('warden', $here->add(new Vector2(0.0, 150.0))));
        $world->addEnemy($factory->create('zombie', $here->add(new Vector2(1500.0, 0.0))));
        $world->addChest(new Chest('bau-1', $here->add(new Vector2(0.0, -120.0)),
            new ChestRarity('madeira', 'Bau de Madeira', 0.85, 'upgrade_de_arma', 1)));

        $snapshot = (new PlaySessionSnapshotProvider($play))->snapshot();
        $kinds = array_count_values(array_map(static fn (PlayEntityView $e): string => $e->kind,
            array_filter($snapshot->world->entities, static fn (PlayEntityView $e): bool => $e->kind !== 'map')));

        self::assertSame(1, $kinds['enemy'], 'o Zombie longe da tela fica de fora');
        self::assertSame(1, $kinds['special_enemy']);
        self::assertSame(1, $kinds['boss']);
        self::assertSame(1, $kinds['chest']);
        self::assertSame('Warden', $snapshot->hud->boss?->name);
        self::assertSame('Onda 1', $snapshot->hud->wave);
        self::assertTrue($snapshot->world->currentPlayer->equals($here));
    }
}
