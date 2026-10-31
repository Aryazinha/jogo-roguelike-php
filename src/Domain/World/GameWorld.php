<?php

declare(strict_types=1);

namespace Jogo\Domain\World;

use InvalidArgumentException;
use Jogo\Domain\Ai\EntityView;
use Jogo\Domain\Ai\SpatialWorldView;
use Jogo\Domain\Ai\WorldEntity;
use Jogo\Domain\Ai\WorldEntityProvider;
use Jogo\Domain\Chest\Chest;
use Jogo\Domain\Enemy\Enemy;
use Jogo\Domain\Enemy\EnemyProjectile;
use Jogo\Domain\Entity\Damageable;
use Jogo\Domain\Entity\Entity;
use Jogo\Domain\Scenery\Breakable;
use Jogo\Domain\Scenery\Pickup;
use Jogo\Domain\Spatial\SpatialHash;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\EnemyTarget;
use Jogo\Domain\Weapon\EnemyTargetResolver;

/**
 * Tudo o que existe no mapa durante uma partida (RF03).
 *
 * É o lado da Frente C do contrato com a A: fornece as entidades para a
 * SpatialWorldView e resolve IDs em alvos de dano, sem a A conhecer Enemy.
 *
 * Inimigos criados durante o combate (filhos do Slime, invocações do boss)
 * entram pela fila e só passam a existir em flush(), que a partida chama
 * num ponto seguro, depois de dano e eventos do passo.
 */
final class GameWorld implements EnemyTargetResolver, WorldEntityProvider
{
    /** @var array<string, Enemy> */
    private array $enemies = [];

    /** @var list<Enemy> */
    private array $queued = [];

    /** @var array<string, EnemyProjectile> */
    private array $projectiles = [];

    /** @var array<string, Chest> */
    private array $chests = [];

    /** @var array<string, Breakable> */
    private array $breakables = [];

    /** @var array<string, Pickup> */
    private array $pickups = [];

    public function __construct(
        private readonly Entity&Damageable $player,
        private readonly MapLayout $layout,
    ) {
        $this->player->moveTo($layout->playerStart());
    }

    public function player(): Entity&Damageable
    {
        return $this->player;
    }

    public function layout(): MapLayout
    {
        return $this->layout;
    }

    public function addEnemy(Enemy $enemy): void
    {
        $this->assertFreeId($enemy->id());
        if (! $enemy->isActive() || $enemy->health()->isDead()) {
            throw new InvalidArgumentException("Inimigo {$enemy->id()} já chegou morto ou inativo.");
        }
        $this->enemies['id:'.$enemy->id()] = $enemy;
    }

    /** Projéteis de inimigos entram na hora: nascem de um sistema, não de um evento de combate. */
    public function addProjectile(EnemyProjectile $projectile): void
    {
        $this->assertFreeId($projectile->id());
        $this->projectiles['id:'.$projectile->id()] = $projectile;
    }

    /** @return list<EnemyProjectile> */
    public function projectiles(): array
    {
        return array_values($this->projectiles);
    }

    public function addChest(Chest $chest): void
    {
        $this->assertFreeId($chest->id());
        $this->chests['id:'.$chest->id()] = $chest;
    }

    /** @return list<Chest> */
    public function chests(): array
    {
        return array_values($this->chests);
    }

    public function addBreakable(Breakable $breakable): void
    {
        $this->assertFreeId($breakable->id());
        $this->breakables['id:'.$breakable->id()] = $breakable;
    }

    /** @return list<Breakable> */
    public function breakables(): array
    {
        return array_values($this->breakables);
    }

    public function breakable(string $id): ?Breakable
    {
        return $this->breakables['id:'.$id] ?? null;
    }

    public function addPickup(Pickup $pickup): void
    {
        $this->assertFreeId($pickup->id());
        $this->pickups['id:'.$pickup->id()] = $pickup;
    }

    /** @return list<Pickup> */
    public function pickups(): array
    {
        return array_values($this->pickups);
    }

    /** Agenda o inimigo para o próximo flush(); seguro de chamar dentro de listeners. */
    public function queueEnemy(Enemy $enemy): void
    {
        $this->assertFreeId($enemy->id());
        $this->queued[] = $enemy;
    }

    /**
     * Ponto seguro do passo: insere os agendados e retira mortos, inativos e
     * projéteis que já acertaram ou chegaram ao fim do alcance, baús já abertos,
     * quebráveis destruídos e itens já pegos.
     *
     * @return list<Enemy> Inimigos retirados, para quem precisar reagir à saída.
     */
    public function flush(): array
    {
        $removed = [];
        foreach ($this->enemies as $key => $enemy) {
            if (! $enemy->isActive() || $enemy->health()->isDead()) {
                $enemy->deactivate();
                $removed[] = $enemy;
                unset($this->enemies[$key]);
            }
        }

        $this->projectiles = array_filter($this->projectiles, static fn (EnemyProjectile $p) => $p->isActive());
        $this->chests = array_filter($this->chests, static fn (Chest $c) => $c->isActive());
        $this->breakables = array_filter($this->breakables, static fn (Breakable $b) => $b->isActive() && ! $b->health()->isDead());
        $this->pickups = array_filter($this->pickups, static fn (Pickup $p) => $p->isActive());

        $queued = $this->queued;
        $this->queued = [];
        foreach ($queued as $enemy) {
            $this->addEnemy($enemy);
        }

        return $removed;
    }

    public function has(string $id): bool
    {
        return isset($this->enemies['id:'.$id]);
    }

    public function enemy(string $id): ?Enemy
    {
        return $this->enemies['id:'.$id] ?? null;
    }

    /** @return list<Enemy> Em ordem de entrada; uma cópia, então pode ser percorrida enquanto o mundo muda. */
    public function enemies(): array
    {
        return array_values($this->enemies);
    }

    /** Inimigos vivos e ativos, que contam para o teto de 300 (RNF06). */
    public function aliveEnemyCount(): int
    {
        $alive = 0;
        foreach ($this->enemies as $enemy) {
            if ($enemy->isActive() && ! $enemy->health()->isDead()) {
                $alive++;
            }
        }

        return $alive + count($this->queued);
    }

    public function resolve(string $id): ?EnemyTarget
    {
        return $this->enemy($id)?->asTarget();
    }

    public function entities(): iterable
    {
        yield new WorldEntity($this->player, 'player', ! $this->player->health()->isDead());
        foreach ($this->enemies() as $enemy) {
            yield new WorldEntity($enemy, 'enemy', ! $enemy->health()->isDead());
        }
        foreach ($this->projectiles() as $projectile) {
            yield new WorldEntity($projectile, 'projectile', null);
        }
        foreach ($this->chests() as $chest) {
            yield new WorldEntity($chest, 'chest', null);
        }
        foreach ($this->breakables() as $breakable) {
            yield new WorldEntity($breakable, 'breakable', ! $breakable->health()->isDead());
        }
        foreach ($this->pickups() as $pickup) {
            yield new WorldEntity($pickup, 'pickup', null);
        }
    }

    /**
     * Retratos das entidades ativas, montados direto, sem passar por WorldEntity.
     * É o que a IA usa a cada passo; com 300 inimigos isso pesa (RNF06).
     *
     * @return list<EntityView>
     */
    public function snapshots(): array
    {
        $views = [];
        foreach ([$this->player, ...$this->enemies] as $entity) {
            if ($entity->isActive()) {
                $position = $entity->position();
                $views[] = new EntityView($entity->id(), new Vector2($position->x(), $position->y()),
                    $entity->collisionRadius(), $entity === $this->player ? 'player' : 'enemy', ! $entity->health()->isDead());
            }
        }
        foreach ($this->projectiles as $projectile) {
            if ($projectile->isActive()) {
                $position = $projectile->position();
                $views[] = new EntityView($projectile->id(), new Vector2($position->x(), $position->y()),
                    $projectile->collisionRadius(), 'projectile', null);
            }
        }
        foreach ($this->chests as $chest) {
            if ($chest->isActive()) {
                $position = $chest->position();
                $views[] = new EntityView($chest->id(), new Vector2($position->x(), $position->y()),
                    $chest->collisionRadius(), 'chest', null);
            }
        }
        foreach ($this->breakables as $breakable) {
            if ($breakable->isActive()) {
                $position = $breakable->position();
                $views[] = new EntityView($breakable->id(), new Vector2($position->x(), $position->y()),
                    $breakable->collisionRadius(), 'breakable', ! $breakable->health()->isDead());
            }
        }
        foreach ($this->pickups as $pickup) {
            if ($pickup->isActive()) {
                $position = $pickup->position();
                $views[] = new EntityView($pickup->id(), new Vector2($position->x(), $position->y()),
                    $pickup->collisionRadius(), 'pickup', null);
            }
        }

        return $views;
    }

    /** Visão de leitura da A sobre este mundo; chame refresh() nela depois de mover entidades. */
    public function createView(SpatialHash $spatial = new SpatialHash): SpatialWorldView
    {
        return new SpatialWorldView($this, fn () => $this->player->position(), $spatial);
    }

    private function assertFreeId(string $id): void
    {
        if ($id === $this->player->id() || isset($this->enemies['id:'.$id])
            || isset($this->projectiles['id:'.$id]) || isset($this->chests['id:'.$id])
            || isset($this->breakables['id:'.$id]) || isset($this->pickups['id:'.$id]) || $this->isQueued($id)) {
            throw new InvalidArgumentException("ID já usado no mundo: {$id}.");
        }
    }

    private function isQueued(string $id): bool
    {
        foreach ($this->queued as $enemy) {
            if ($enemy->id() === $id) {
                return true;
            }
        }

        return false;
    }
}
