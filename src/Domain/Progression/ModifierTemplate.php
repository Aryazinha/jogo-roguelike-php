<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression;

use InvalidArgumentException;
use Jogo\Domain\Value\ModifierType;
use Jogo\Domain\Value\StatModifier;
use Jogo\Domain\Value\Stats;

/** Efeito de configuração ainda sem origem; a origem é atribuída a cada concessão. */
final readonly class ModifierTemplate
{
    public function __construct(public string $attribute, public ModifierType $type, public float $value)
    {
        Stats::assertAttribute($attribute);
        if (! is_finite($value)) {
            throw new InvalidArgumentException('O valor do modificador deve ser finito.');
        }
    }

    public function toModifier(string $source): StatModifier
    {
        return new StatModifier($this->attribute, $this->type, $this->value, $source);
    }
}
