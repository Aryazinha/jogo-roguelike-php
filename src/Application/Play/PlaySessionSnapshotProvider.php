<?php

declare(strict_types=1);

namespace Jogo\Application\Play;

use Jogo\Application\Graphical\ProgressionSnapshotProvider;
use Jogo\Domain\Enemy\EnemyCategory;
use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Play\BossHudSnapshot;
use Jogo\Infrastructure\Play\PlayEntityView;
use Jogo\Infrastructure\Play\PlayHudSnapshot;
use Jogo\Infrastructure\Play\PlaySnapshot;
use Jogo\Infrastructure\Play\PlaySnapshotProviderInterface;
use Jogo\Infrastructure\Play\PlayWorldSnapshot;

/**
 * O que a Frente D desenha numa partida real: o HUD da B (pelo provider da
 * D) mais o mundo da C e os ataques da A, perto do jogador.
 *
 * Só entra o que cabe na tela com folga (VIEW_HALF_*): com 300 inimigos e um
 * mapa de 4000 × 4000, desenhar tudo custaria quadros sem mostrar nada a mais.
 */
final class PlaySessionSnapshotProvider implements PlaySnapshotProviderInterface
{
    private const VIEW_HALF_WIDTH = 480.0;

    private const VIEW_HALF_HEIGHT = 300.0;

    private const TILE = 64.0;

    private readonly ProgressionSnapshotProvider $progression;

    /** @var array<string, Vector2> Posição de cada entidade no passo anterior, para interpolar. */
    private array $previous = [];

    private Vector2 $previousPlayer;

    public function __construct(private readonly PlaySession $play)
    {
        $this->progression = new ProgressionSnapshotProvider($play->progression);
        $this->previousPlayer = $play->progression->player->position();
    }

    /** Chamado no começo de cada passo fixo, antes de tudo andar. */
    public function capturePrevious(): void
    {
        $this->previousPlayer = $this->play->progression->player->position();
        $this->previous = [];
        foreach ($this->play->content->world->enemies() as $enemy) {
            $this->previous[$enemy->id()] = $enemy->position();
        }
    }

    /** @phpstan-impure */
    public function snapshot(): PlaySnapshot
    {
        $player = $this->play->progression->player->position();
        $content = $this->play->content;
        $world = $content->world;
        $entities = $this->tiles($player);

        foreach ($world->breakables() as $breakable) {
            if ($breakable->isActive()) {
                $this->add($entities, $breakable->blocksMovement() ? 'breakable' : 'scenery', $breakable->id(),
                    $breakable->position(), $breakable->collisionRadius(), $player);
            }
        }
        foreach ($world->chests() as $chest) {
            if (! $chest->isOpened()) {
                $this->add($entities, 'chest', $chest->id(), $chest->position(), $chest->collisionRadius(), $player);
            }
        }
        foreach ($world->pickups() as $pickup) {
            if ($pickup->isActive()) {
                $this->add($entities, 'pickup', $pickup->id(), $pickup->position(), $pickup->collisionRadius(), $player);
            }
        }
        $boss = null;
        foreach ($world->enemies() as $enemy) {
            if (! $enemy->isActive() || $enemy->health()->current() <= 0.0) {
                continue;
            }
            $archetype = $enemy->archetype();
            if ($enemy->isBoss()) {
                $boss = new BossHudSnapshot($archetype->name, $enemy->health()->current(), $enemy->health()->maximum());
            }
            $kind = $enemy->isBoss() ? 'boss' : ($archetype->category === EnemyCategory::SPECIAL ? 'special_enemy' : 'enemy');
            $this->add($entities, $kind, $enemy->id(), $enemy->position(), $enemy->collisionRadius(), $player);
        }
        foreach ($world->projectiles() as $arrow) {
            $this->add($entities, 'projectile', $arrow->id(), $arrow->position(), $arrow->collisionRadius(), $player);
        }
        foreach ($this->play->combat->projectiles() as $index => $projectile) {
            $this->add($entities, 'projectile', 'jogador-'.$index, $projectile->position(), 4.0, $player);
        }
        foreach ($this->play->combat->charges() as $index => $charge) {
            $this->add($entities, 'tnt', 'tnt-'.$index, $charge->position, 10.0, $player);
        }

        $hud = $this->progression->snapshot()->hud;
        $event = $content->events->active();
        $wave = 'Onda '.($content->waves->currentBandIndex() + 1).($event === null ? '' : ' - '.$event->name);

        return new PlaySnapshot(
            new PlayWorldSnapshot($this->previousPlayer, $player, $entities,
                $this->play->progression->player->collisionRadius()),
            new PlayHudSnapshot($hud->health, $hud->maxHealth, $hud->xp, $hud->xpToNextLevel, $hud->level,
                $hud->elapsedSeconds, $hud->weapons, $hud->passives, $boss, $wave),
        );
    }

    /**
     * Piso só na área da tela, alinhado à grade do mapa.
     *
     * @return list<PlayEntityView>
     */
    private function tiles(Vector2 $player): array
    {
        $bounds = $this->play->content->world->layout()->bounds();
        $tiles = [];
        $startX = max($bounds->minX, floor(($player->x() - self::VIEW_HALF_WIDTH) / self::TILE) * self::TILE);
        $startY = max($bounds->minY, floor(($player->y() - self::VIEW_HALF_HEIGHT) / self::TILE) * self::TILE);
        for ($x = $startX; $x < min($bounds->maxX, $player->x() + self::VIEW_HALF_WIDTH); $x += self::TILE) {
            for ($y = $startY; $y < min($bounds->maxY, $player->y() + self::VIEW_HALF_HEIGHT); $y += self::TILE) {
                $center = new Vector2($x + self::TILE / 2, $y + self::TILE / 2);
                $tiles[] = new PlayEntityView('map', $center, $center, self::TILE / 2);
            }
        }

        return $tiles;
    }

    /** @param list<PlayEntityView> $entities */
    private function add(array &$entities, string $kind, string $id, Vector2 $position, float $radius, Vector2 $player): void
    {
        if (abs($position->x() - $player->x()) > self::VIEW_HALF_WIDTH + $radius
            || abs($position->y() - $player->y()) > self::VIEW_HALF_HEIGHT + $radius) {
            return;
        }
        $entities[] = new PlayEntityView($kind, $this->previous[$id] ?? $position, $position, max(3.0, $radius));
    }
}
