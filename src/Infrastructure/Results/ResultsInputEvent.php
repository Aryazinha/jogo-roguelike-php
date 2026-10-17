<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Results;

use InvalidArgumentException;

final readonly class ResultsInputEvent
{
    public function __construct(
        public string $text = '',
        public int $horizontal = 0,
        public bool $confirm = false,
        public bool $back = false,
        public bool $backspace = false,
        public bool $click = false,
        public ?int $mouseX = null,
        public ?int $mouseY = null,
    ) {
        if (
            abs($horizontal) > 1
            || ($mouseX === null) !== ($mouseY === null)
        ) {
            throw new InvalidArgumentException(
                'Entrada de resultado invalida.'
            );
        }
    }
}
