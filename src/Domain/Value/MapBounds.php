<?php

declare(strict_types=1);

namespace Jogo\Domain\Value;

use InvalidArgumentException;

/** Retângulo permitido para a posição, em pixels do mundo, sem conteúdo de mapa. */
final readonly class MapBounds
{
    public function __construct(
        public float $minX,
        public float $minY,
        public float $maxX,
        public float $maxY,
    ) {
        foreach ([$minX, $minY, $maxX, $maxY] as $coordinate) {
            if (! is_finite($coordinate)) {
                throw new InvalidArgumentException('Os limites do mapa devem ser números finitos.');
            }
        }

        if ($minX > $maxX || $minY > $maxY) {
            throw new InvalidArgumentException('Cada limite mínimo deve ser menor ou igual ao máximo.');
        }
    }

    public function clamp(Vector2 $position): Vector2
    {
        return new Vector2(
            max($this->minX, min($this->maxX, $position->x())),
            max($this->minY, min($this->maxY, $position->y())),
        );
    }
}
