<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon;

use InvalidArgumentException;
use Jogo\Domain\System\CollisionSystem;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\Targeting\TargetingContext;

final class AttackGeometry
{
    public static function inSector(TargetingContext $context, Vector2 $position, float $angleDegrees): bool
    {
        CombatValidation::positive($angleDegrees, 'angleDegrees');
        if ($angleDegrees > 360.0) {
            throw new InvalidArgumentException('angleDegrees deve ser no máximo 360.');
        }
        if (! CollisionSystem::circlesIntersect($context->origin, $context->range, $position, 0.0)) {
            return false;
        }
        $offset = $position->subtract($context->origin);
        $length = $offset->length();
        if ($length === 0.0) {
            return true;
        }
        $x = $offset->x() / $length;
        $y = $offset->y() / $length;
        $dot = $context->facing->x() * $x + $context->facing->y() * $y;
        $cross = $context->facing->x() * $y - $context->facing->y() * $x;
        $angle = abs(rad2deg(atan2($cross, $dot)));
        $half = $angleDegrees * 0.5;

        // Margem de arredondamento angular, sem aumentar alcance ou raio de colisão.
        return $angle <= $half + 8.0 * PHP_FLOAT_EPSILON * max(1.0, $half);
    }
}
