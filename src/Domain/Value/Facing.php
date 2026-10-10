<?php

declare(strict_types=1);

namespace Jogo\Domain\Value;

use InvalidArgumentException;

/** Componente lógico do olhar; a direção inicial é sempre fornecida pelo chamador. */
final class Facing
{
    public function __construct(private Vector2 $direction)
    {
        self::assertUnitDirection($direction);
    }

    public function direction(): Vector2
    {
        return $this->direction;
    }

    /** null conserva o último olhar, inclusive quando o jogador está parado. */
    public function update(?Vector2 $direction): void
    {
        if ($direction === null) {
            return;
        }

        self::assertUnitDirection($direction);
        $this->direction = $direction;
    }

    private static function assertUnitDirection(Vector2 $direction): void
    {
        if (abs($direction->length() - 1.0) > 1.0E-9) {
            throw new InvalidArgumentException('A direção do olhar deve ser um vetor unitário não nulo.');
        }
    }
}
