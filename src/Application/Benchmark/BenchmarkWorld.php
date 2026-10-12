<?php

declare(strict_types=1);

namespace Jogo\Application\Benchmark;

use Jogo\Domain\Ai\WorldEntity;
use Jogo\Domain\Ai\WorldEntityProvider;
use Jogo\Domain\Entity\Collidable;
use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Domain\System\MovementSystem;
use Jogo\Domain\Value\InputState;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\AttackContextProvider;
use Jogo\Domain\Weapon\EnemyTarget;
use Jogo\Domain\Weapon\EnemyTargetResolver;
use Jogo\Domain\Weapon\Targeting\TargetingContext;

final class BenchmarkWorld implements AttackContextProvider, EnemyTargetResolver, WorldEntityProvider
{
    public readonly BenchmarkPlayer $player;

    /** @var array<string, BenchmarkEnemy> */
    private array $enemies = [];

    private readonly InputState $right;

    private readonly InputState $left;

    public function __construct(int $seed, int $count)
    {
        $this->player = new BenchmarkPlayer;
        $random = new SeededRandomIndex($seed);
        for ($index = 0; $index < $count; $index++) {
            // Malha 20 × 15 no cenário oficial, com jitter de [-2, 2] px por eixo.
            $position = new Vector2(32.0 * ($index % 20) - 304.0 + $random->nextIndex(5) - 2,
                32.0 * intdiv($index, 20) - 240.0 + $random->nextIndex(5) - 2);
            $id = sprintf('a10-enemy-%06d', $index);
            $this->enemies[$id] = new BenchmarkEnemy($id, $position);
        }
        $this->right = new InputState(new Vector2(1.0, 0.0));
        $this->left = new InputState(new Vector2(-1.0, 0.0));
    }

    public function context(float $range): TargetingContext
    {
        return new TargetingContext($this->player->position(), $this->player->facing->direction(), $range);
    }

    public function resolve(string $entityId): ?EnemyTarget
    {
        $enemy = $this->enemies[$entityId] ?? null;

        return $enemy === null ? null : new EnemyTarget($enemy, 'benchmark_enemy', false);
    }

    public function entities(): iterable
    {
        yield new WorldEntity($this->player, 'player', ! $this->player->health()->isDead());
        foreach ($this->enemies as $enemy) {
            yield new WorldEntity($enemy, 'enemy', ! $enemy->health()->isDead());
        }
    }

    /** @return iterable<Collidable> */
    public function collidables(): iterable
    {
        yield $this->player;
        yield from $this->enemies;
    }

    public function moveEnemies(MovementSystem $movement, float $dt, int $step): void
    {
        $input = intdiv($step - 1, 120) % 2 === 0 ? $this->right : $this->left;
        foreach ($this->enemies as $enemy) {
            $movement->update($enemy, $enemy->facing, $input, 12.0, $dt, $enemy->isActive());
        }
    }

    public function activeEnemies(): int
    {
        $active = 0;
        foreach ($this->enemies as $enemy) {
            if ($enemy->isActive() && ! $enemy->health()->isDead()) {
                $active++;
            }
        }

        return $active;
    }

    /** @return list<array{string, float, float, float, bool}> */
    public function state(): array
    {
        return array_map(static fn (BenchmarkEnemy $e): array => [$e->id(), $e->position()->x(), $e->position()->y(),
            $e->health()->current(), $e->isActive()], array_values($this->enemies));
    }

    public function clear(): void
    {
        $this->enemies = [];
        $this->player->deactivate();
    }
}
