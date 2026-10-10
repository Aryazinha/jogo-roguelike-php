<?php

declare(strict_types=1);

namespace Jogo\Domain\Value;

use InvalidArgumentException;

/** Dados lógicos imutáveis; a origem da entrada não pertence ao domínio. */
final readonly class InputState
{
    public function __construct(
        public Vector2 $movement,
        public ?Vector2 $facing = null,
        public bool $pauseRequested = false,
    ) {
        if (abs($movement->x()) > 1.0 || abs($movement->y()) > 1.0) {
            throw new InvalidArgumentException('Os eixos de movimento devem estar entre -1 e 1.');
        }

        if ($facing !== null && abs($facing->length() - 1.0) > 1.0E-9) {
            throw new InvalidArgumentException('A direção do olhar deve ser unitária ou null.');
        }
    }
}
