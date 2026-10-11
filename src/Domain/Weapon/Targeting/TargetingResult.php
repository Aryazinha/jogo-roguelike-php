<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon\Targeting;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Vector2;

final readonly class TargetingResult
{
    public function __construct(public Vector2 $direction, public Vector2 $position, public ?string $entityId)
    {
        CombatValidation::optionalId($entityId, 'entityId');
        $length = $direction->length();
        if ($length !== 0.0 && abs($length - 1.0) > 1.0E-9) {
            throw new InvalidArgumentException('direction deve ser zero ou unitária.');
        }
        if ($entityId !== null && $length === 0.0) {
            throw new InvalidArgumentException('direction de um alvo individual não pode ser zero.');
        }
    }
}
