<?php

declare(strict_types=1);

namespace Jogo\Domain\Value;

use InvalidArgumentException;

final class StatResolver
{
    /** @param list<StatModifier> $modifiers */
    public function resolve(Stats $base, array $modifiers, bool $ignoreMissing = false): Stats
    {
        $sources = [];
        foreach ($modifiers as $modifier) {
            $key = 'source:'.$modifier->source;
            if (isset($sources[$key])) {
                throw new InvalidArgumentException("source duplicada: {$modifier->source}.");
            }
            $sources[$key] = true;
            if (! $ignoreMissing && ! $base->has($modifier->attribute)) {
                throw new InvalidArgumentException("{$modifier->source}: atributo ausente {$modifier->attribute}.");
            }
        }
        usort($modifiers, static fn (StatModifier $a, StatModifier $b): int => strcmp($a->source, $b->source));
        $values = $base->values();
        foreach ($values as $attribute => $value) {
            $flat = 0.0;
            $percent = 1.0;
            foreach ($modifiers as $modifier) {
                if ($modifier->attribute !== $attribute) {
                    continue;
                }
                if ($modifier->type === ModifierType::FLAT) {
                    $flat += $modifier->value;
                } else {
                    $percent *= 1.0 + $modifier->value;
                }
            }
            $values[$attribute] = ($value + $flat) * $percent;
        }

        return new Stats($values);
    }
}
