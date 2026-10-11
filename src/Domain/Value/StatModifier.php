<?php

declare(strict_types=1);

namespace Jogo\Domain\Value;

use InvalidArgumentException;

final readonly class StatModifier
{
    public function __construct(public string $attribute, public ModifierType $type, public float $value, public string $source)
    {
        Stats::assertAttribute($attribute);
        CombatValidation::requiredId($source, 'source');
        if (! is_finite($value)) {
            throw new InvalidArgumentException('value do modificador deve ser finito.');
        }
    }
}
