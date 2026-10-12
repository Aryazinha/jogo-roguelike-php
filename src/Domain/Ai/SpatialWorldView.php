<?php

declare(strict_types=1);

namespace Jogo\Domain\Ai;

use Closure;
use InvalidArgumentException;
use Jogo\Domain\Spatial\SpatialHash;
use Jogo\Domain\System\CollisionSystem;
use Jogo\Domain\System\GeometryMetrics;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\ProjectileCollisionView;

/** Adaptador de leitura: usa a grade da A4 e entrega somente snapshots. */
final class SpatialWorldView implements ProjectileCollisionView, WorldView
{
    /** @var Closure(): Vector2 */
    private readonly Closure $player;

    /** @param callable(): Vector2 $player */
    public function __construct(
        private readonly WorldEntityProvider $provider,
        callable $player,
        private readonly SpatialHash $spatial = new SpatialHash,
        private readonly ?GeometryMetrics $metrics = null,
    ) {
        $this->player = Closure::fromCallable($player);
    }

    public function playerPosition(): Vector2
    {
        $position = ($this->player)();

        return new Vector2($position->x(), $position->y());
    }

    public function neighbors(Vector2 $center, float $radius): array
    {
        $views = $this->query($center, $radius);

        return array_values(array_filter($views, fn (EntityView $view): bool => CollisionSystem::circlesIntersect($center, $radius, $view->position, 0.0, $this->metrics)));
    }

    public function candidates(Vector2 $start, Vector2 $end): array
    {
        $difference = $end->subtract($start);
        $midpoint = $start->add($difference->scale(0.5));

        return $this->query($midpoint, $difference->length() * 0.5);
    }

    /** @return list<EntityView> */
    private function query(Vector2 $center, float $radius): array
    {
        $records = [];
        $entities = [];
        // Consulta fresca inclusive após morte ou movimento dentro do mesmo passo.
        foreach ($this->provider->entities() as $record) {
            if (! $record->entity->isActive()) {
                continue;
            }
            $key = 'id:'.$record->entity->id();
            if (isset($records[$key])) {
                throw new InvalidArgumentException('ID duplicado no provider: '.$record->entity->id());
            }
            $records[$key] = $record;
            $entities[] = $record->entity;
        }
        $this->spatial->rebuild($entities);
        $views = [];
        foreach ($this->spatial->queryRadius($center, $radius)->entities as $entity) {
            $views[] = $records['id:'.$entity->id()]->snapshot();
        }

        return $views;
    }
}
