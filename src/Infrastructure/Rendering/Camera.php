<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Rendering;

use InvalidArgumentException;
use Jogo\Domain\Value\MapBounds;
use Jogo\Domain\Value\Vector2;

final readonly class Camera
{
    public function __construct(
        public int $width,
        public int $height,
        private MapBounds $bounds,
    ) {
        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException('Dimensoes da camera invalidas.');
        }
    }

    public static function interpolate(
        Vector2 $previous,
        Vector2 $current,
        float $alpha
    ): Vector2 {
        if (! is_finite($alpha) || $alpha < 0.0 || $alpha > 1.0) {
            throw new InvalidArgumentException('Alpha deve estar entre 0 e 1.');
        }

        return new Vector2(
            $previous->x() + ($current->x() - $previous->x()) * $alpha,
            $previous->y() + ($current->y() - $previous->y()) * $alpha,
        );
    }

    public function topLeft(Vector2 $focus): Vector2
    {
        $worldWidth = $this->bounds->maxX - $this->bounds->minX;
        $worldHeight = $this->bounds->maxY - $this->bounds->minY;

        $x = $worldWidth <= $this->width
            ? $this->bounds->minX - ($this->width - $worldWidth) / 2.0
            : max(
                $this->bounds->minX,
                min($this->bounds->maxX - $this->width, $focus->x() - $this->width / 2.0)
            );

        $y = $worldHeight <= $this->height
            ? $this->bounds->minY - ($this->height - $worldHeight) / 2.0
            : max(
                $this->bounds->minY,
                min($this->bounds->maxY - $this->height, $focus->y() - $this->height / 2.0)
            );

        return new Vector2($x, $y);
    }

    public function worldToScreen(Vector2 $world, Vector2 $focus): Vector2
    {
        return $world->subtract($this->topLeft($focus));
    }

    public function screenToWorld(Vector2 $screen, Vector2 $focus): Vector2
    {
        return $screen->add($this->topLeft($focus));
    }
}
