<?php

declare(strict_types=1);

namespace Jogo\Domain\Value;

use InvalidArgumentException;

/** @internal Valida os campos comuns de dano e eventos, sem transformar os valores. */
final class CombatValidation
{
    public static function requiredId(string $id, string $field): void
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException("{$field} não pode estar vazio.");
        }
    }

    public static function optionalId(?string $id, string $field): void
    {
        if ($id !== null) {
            self::requiredId($id, $field);
        }
    }

    public static function nonNegative(float $value, string $field): void
    {
        if (! is_finite($value) || $value < 0.0) {
            throw new InvalidArgumentException("{$field} deve ser finito e maior ou igual a zero.");
        }
    }

    public static function positive(float $value, string $field): void
    {
        self::nonNegative($value, $field);

        if ($value === 0.0) {
            throw new InvalidArgumentException("{$field} deve ser maior que zero.");
        }
    }
}
