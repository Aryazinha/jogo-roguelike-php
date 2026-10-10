<?php

declare(strict_types=1);

namespace Jogo\Domain\System;

use InvalidArgumentException;
use Jogo\Domain\Entity\Collidable;
use Jogo\Domain\Value\Vector2;

/** Fase estreita: confirma interseções geométricas, sem consequências de gameplay. */
final class CollisionSystem
{
    public function intersects(Collidable $first, Collidable $second): bool
    {
        if (! $first->isActive() || ! $second->isActive() || $first->id() === $second->id()) {
            return false;
        }

        return self::circlesIntersect(
            $first->position(), $first->collisionRadius(),
            $second->position(), $second->collisionRadius(),
        );
    }

    /** Tangência exata conta como interseção, inclusive para círculos de raio zero. */
    public static function circlesIntersect(Vector2 $first, float $firstRadius, Vector2 $second, float $secondRadius): bool
    {
        foreach ([$firstRadius, $secondRadius] as $radius) {
            if (! is_finite($radius) || $radius < 0.0) {
                throw new InvalidArgumentException('O raio deve ser finito e maior ou igual a zero.');
            }
        }

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
