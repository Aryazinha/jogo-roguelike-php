<?php

declare(strict_types=1);

namespace Jogo\Domain\System;

use InvalidArgumentException;
use Jogo\Domain\Entity\Collidable;
use Jogo\Domain\Value\Vector2;

/** Fase estreita: confirma interseções geométricas, sem consequências de gameplay. */
final class CollisionSystem
{
    public function __construct(private readonly ?GeometryMetrics $metrics = null) {}

    /** Primeiro contato de um ponto em movimento com um círculo, como fração em [0, 1]. */
    public static function segmentCircleFirstHit(Vector2 $start, Vector2 $end, Vector2 $center, float $radius, ?GeometryMetrics $metrics = null): ?float
    {
        $inside = self::circlesIntersect($start, 0.0, $center, $radius, $metrics);
        $metrics?->recordSegment();
        if ($inside) {
            return 0.0;
        }
        $offset = $end->subtract($start);
        $length = $offset->length();
        if (! is_finite($length)) {
            throw new InvalidArgumentException('O comprimento do segmento deve ser finito.');
        }
        if ($length === 0.0) {
            return null;
        }
        $x = $offset->x() / $length;
        $y = $offset->y() / $length;
        $relative = $center->subtract($start);
        $projection = $relative->x() * $x + $relative->y() * $y;
        $perpendicular = abs($relative->x() * $y - $relative->y() * $x);
        if (! is_finite($projection) || ! is_finite($perpendicular)) {
            throw new InvalidArgumentException('A geometria derivada do segmento deve ser finita.');
        }
        if ($perpendicular > $radius) {
            return null;
        }
        $penetration = $radius === 0.0 ? 0.0 : $radius * sqrt(max(0.0, 1.0 - ($perpendicular / $radius) ** 2));
        $entry = $projection - $penetration;

        return $entry >= 0.0 && $entry <= $length ? $entry / $length : null;
    }

    public function intersects(Collidable $first, Collidable $second): bool
    {
        if (! $first->isActive() || ! $second->isActive() || $first->id() === $second->id()) {
            return false;
        }

        return self::circlesIntersect(
            $first->position(), $first->collisionRadius(),
            $second->position(), $second->collisionRadius(),
            $this->metrics,
        );
    }

    /** Tangência exata conta como interseção, inclusive para círculos de raio zero. */
    public static function circlesIntersect(Vector2 $first, float $firstRadius, Vector2 $second, float $secondRadius, ?GeometryMetrics $metrics = null): bool
    {
        foreach ([$firstRadius, $secondRadius] as $radius) {
            if (! is_finite($radius) || $radius < 0.0) {
                throw new InvalidArgumentException('O raio deve ser finito e maior ou igual a zero.');
            }
        }

        $metrics?->recordCircle();
        $dx = $first->x() - $second->x();
        $dy = $first->y() - $second->y();
        $sum = $firstRadius + $secondRadius;
        $distanceSquared = $dx * $dx + $dy * $dy;
        $radiusSquared = $sum * $sum;

        if (is_finite($distanceSquared) && is_finite($radiusSquared)
            && ($distanceSquared >= PHP_FLOAT_MIN || ($dx === 0.0 && $dy === 0.0))
            && ($radiusSquared >= PHP_FLOAT_MIN || $sum === 0.0)) {
            return $distanceSquared <= $radiusSquared;
        }

        // Reescala apenas nos extremos numéricos para evitar INF <= INF ou perda por underflow.
        $scale = max(abs($dx), abs($dy), $firstRadius, $secondRadius);

        if (! is_finite($scale)) {
            $scale = max(abs($first->x()), abs($first->y()), abs($second->x()), abs($second->y()),
                $firstRadius, $secondRadius);
        }

        if ($scale === 0.0) {
            return true;
        }

        $dx = is_finite($dx) ? $dx / $scale : $first->x() / $scale - $second->x() / $scale;
        $dy = is_finite($dy) ? $dy / $scale : $first->y() / $scale - $second->y() / $scale;
        $sum = $firstRadius / $scale + $secondRadius / $scale;

        return $dx * $dx + $dy * $dy <= $sum * $sum;
    }
}
