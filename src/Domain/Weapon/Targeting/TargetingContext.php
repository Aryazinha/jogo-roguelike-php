<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon\Targeting;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Vector2;

final readonly class TargetingContext
{
    public function __construct(public Vector2 $origin, public Vector2 $facing, public float $range)
    {
        CombatValidation::positive($range, 'range');
        if (abs($facing->length() - 1.0) > 1.0E-9) {
            throw new InvalidArgumentException('facing deve ser uma direção unitária não nula.');
        }
    }
}
