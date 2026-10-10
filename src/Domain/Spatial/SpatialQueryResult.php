<?php

declare(strict_types=1);

namespace Jogo\Domain\Spatial;

use Jogo\Domain\Entity\Collidable;

/** Resultado e métricas de uma consulta; referências de domínio, não visões de apresentação. */
final readonly class SpatialQueryResult
{
    /** @param list<Collidable> $entities */
    public function __construct(
        public array $entities,
        public int $candidatesExamined,
        public int $cellsVisited,
    ) {}

    public function resultCount(): int
    {
        return count($this->entities);
    }
}
