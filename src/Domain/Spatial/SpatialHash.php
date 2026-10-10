<?php

declare(strict_types=1);

namespace Jogo\Domain\Spatial;

use InvalidArgumentException;
use Jogo\Domain\Entity\Collidable;
use Jogo\Domain\System\CollisionSystem;
use Jogo\Domain\Value\Vector2;

/** Fase ampla com círculos registrados em todas as células do retângulo envolvente. */
final class SpatialHash
{
    public const DEFAULT_CELL_SIZE = 64.0;

    // Orçamento técnico ajustável para não expandir intervalos inviáveis; não é limite de mapa.
    public const DEFAULT_MAX_CELLS_PER_OPERATION = 100_000;

    /** @var array<string, array<string, Collidable>> */
    private array $cells = [];

    /** @var array<string, Collidable> */
    private array $entities = [];

    public function __construct(
        private readonly float $cellSize = self::DEFAULT_CELL_SIZE,
        private readonly int $maxCellsPerOperation = self::DEFAULT_MAX_CELLS_PER_OPERATION,
    ) {
        if (! is_finite($cellSize) || $cellSize <= 0.0) {
            throw new InvalidArgumentException('O tamanho de célula deve ser finito e positivo.');
        }

        if ($maxCellsPerOperation < 1) {
            throw new InvalidArgumentException('O orçamento de células por operação deve ser positivo.');
        }
    }

    public function cellSize(): float
    {
        return $this->cellSize;
    }

    public function entityCount(): int
    {
        return count($this->entities);
    }

    public function insert(Collidable $entity): void
    {
        if (! $entity->isActive()) {
            return;
        }

        $id = $entity->id();

        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador da entidade não pode estar vazio.');
        }

        // O prefixo preserva IDs numéricos como strings e evita conversão implícita das chaves.
        $key = 'id:'.$id;

        if (isset($this->entities[$key])) {
            throw new InvalidArgumentException("Já existe uma entidade com o identificador {$id} na grade.");
        }

        [$minX, $minY, $maxX, $maxY] = $this->circleRange($entity->position(), $entity->collisionRadius());
        $this->assertCellBudget($minX, $minY, $maxX, $maxY);
        $this->entities[$key] = $entity;

        for ($x = $minX; $x <= $maxX; $x++) {
            for ($y = $minY; $y <= $maxY; $y++) {
                $this->cells[$x.':'.$y][$key] = $entity;
            }
        }
    }

    public function clear(): void
    {
        $this->cells = [];
        $this->entities = [];
    }

    /**
     * Reconstrói depois do movimento. Uma entrada inválida conserva a grade anterior.
     *
     * @param  iterable<Collidable>  $entities
     */
    public function rebuild(iterable $entities): void
    {
        $next = new self($this->cellSize, $this->maxCellsPerOperation);

        foreach ($entities as $entity) {
            $next->insert($entity);
        }

        $this->cells = $next->cells;
        $this->entities = $next->entities;
    }

    /** Devolve candidatos das nove células; não confirma distância nem colisão. */
    public function queryNeighbors(Vector2 $position, ?string $excludeId = null): SpatialQueryResult
    {
        $x = $this->cellIndex($position->x());
        $y = $this->cellIndex($position->y());

        return $this->collect($x - 1, $y - 1, $x + 1, $y + 1, $excludeId);
    }

    /** Confirma interseção entre o círculo da consulta e os círculos das entidades. */
    public function queryRadius(Vector2 $center, float $radius, ?string $excludeId = null): SpatialQueryResult
    {
        [$minX, $minY, $maxX, $maxY] = $this->circleRange($center, $radius);
        $candidates = $this->collect($minX, $minY, $maxX, $maxY, $excludeId);
        $matches = [];

        foreach ($candidates->entities as $entity) {
            if (CollisionSystem::circlesIntersect($center, $radius, $entity->position(), $entity->collisionRadius())) {
                $matches[] = $entity;
            }
        }

        return new SpatialQueryResult($matches, $candidates->candidatesExamined, $candidates->cellsVisited);
    }

    private function collect(int $minX, int $minY, int $maxX, int $maxY, ?string $excludeId): SpatialQueryResult
    {
        $this->assertCellBudget($minX, $minY, $maxX, $maxY);
        $candidates = [];
        $visited = 0;

        for ($x = $minX; $x <= $maxX; $x++) {
            for ($y = $minY; $y <= $maxY; $y++) {
                $visited++;

                foreach ($this->cells[$x.':'.$y] ?? [] as $key => $entity) {
                    if ($entity->isActive() && $entity->id() !== $excludeId) {
                        $candidates[$key] = $entity;
                    }
                }
            }
        }

        ksort($candidates, SORT_STRING);

        return new SpatialQueryResult(array_values($candidates), count($candidates), $visited);
    }

    /** @return array{int, int, int, int} */
    private function circleRange(Vector2 $center, float $radius): array
    {
        if (! is_finite($radius) || $radius < 0.0) {
            throw new InvalidArgumentException('O raio deve ser finito e maior ou igual a zero.');
        }

        return [$this->cellIndex($center->x() - $radius), $this->cellIndex($center->y() - $radius),
            $this->cellIndex($center->x() + $radius), $this->cellIndex($center->y() + $radius)];
    }

    private function cellIndex(float $coordinate): int
    {
        $index = floor($coordinate / $this->cellSize);

        if (! is_finite($index) || $index <= PHP_INT_MIN || $index >= PHP_INT_MAX) {
            throw new InvalidArgumentException('A coordenada excede a faixa de índices inteiros da grade.');
        }

        return (int) $index;
    }

    private function assertCellBudget(int $minX, int $minY, int $maxX, int $maxY): void
    {
        if ($maxX - $minX >= $this->maxCellsPerOperation || $maxY - $minY >= $this->maxCellsPerOperation) {
            throw new InvalidArgumentException('A operação excede o orçamento configurado de células.');
        }

        $width = $maxX - $minX + 1;
        $height = $maxY - $minY + 1;

        if ($width * $height > $this->maxCellsPerOperation) {
            throw new InvalidArgumentException('A operação excede o orçamento configurado de células.');
        }
    }
}
