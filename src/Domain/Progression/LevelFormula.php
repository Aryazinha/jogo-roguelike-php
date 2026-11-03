<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression;

use InvalidArgumentException;

/** XP necessário para sair do nível informado (RF13, fórmula sugerida pelo enunciado). */
final class LevelFormula
{
    public static function requiredXp(int $level): int
    {
        if ($level < 0) {
            throw new InvalidArgumentException('O nível não pode ser negativo.');
        }

        return match (true) {
            $level <= 15 => 2 * $level + 7,
            $level <= 30 => 5 * $level - 38,
            default => 9 * $level - 158,
        };
    }
}
