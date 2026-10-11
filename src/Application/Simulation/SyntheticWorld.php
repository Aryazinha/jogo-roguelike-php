<?php

declare(strict_types=1);

namespace Jogo\Application\Simulation;

use Jogo\Domain\Ai\WorldEntity;
use Jogo\Domain\Ai\WorldEntityProvider;
use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\AttackContextProvider;
use Jogo\Domain\Weapon\EnemyTarget;
use Jogo\Domain\Weapon\EnemyTargetResolver;
use Jogo\Domain\Weapon\Targeting\TargetingContext;

/** Fixture executável da ferramenta, isolada dos inimigos de gameplay. */
final class SyntheticWorld implements AttackContextProvider, EnemyTargetResolver, WorldEntityProvider
{
    /** @var array<string, BenchmarkTarget> */
    private array $targets = [];

    public function __construct(int $seed, int $count)
    {
        $random = new SeededRandomIndex($seed);
        for ($index = 0; $index < $count; $index++) {
            $id = sprintf('benchmark-%06d', $index);
            $this->targets[$id] = new BenchmarkTarget($id, new Vector2(60.0 + $random->nextIndex(180), $random->nextIndex(301) - 150.0));
        }
    }

    public function entities(): iterable
    {
        foreach ($this->targets as $entity) {
            yield new WorldEntity($entity, 'enemy', ! $entity->health()->isDead());
        }
    }

    public function resolve(string $id): ?EnemyTarget
    {
        $target = $this->targets[$id] ?? null;

        return $target === null ? null : new EnemyTarget($target, 'benchmark_target', false);
    }

    public function context(float $range): TargetingContext
    {
        return new TargetingContext(Vector2::zero(), new Vector2(1.0, 0.0), $range);
    }

    public function activeCount(): int
    {
        return count(array_filter($this->targets, static fn (BenchmarkTarget $target): bool => $target->isActive()));
    }

    /** @return list<array{id: string, x: float, y: float, health: float, active: bool}> */
    public function state(): array
    {
        return array_values(array_map(static fn (BenchmarkTarget $target): array => ['id' => $target->id(),
            'x' => $target->position()->x(), 'y' => $target->position()->y(), 'health' => $target->health()->current(),
            'active' => $target->isActive()], $this->targets));
    }

    public function clear(): void
    {
        $this->targets = [];
    }
}
