<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Menu;

use InvalidArgumentException;

final readonly class MenuInputEvent
{
    public function __construct(
        public int $vertical = 0,
        public int $horizontal = 0,
        public bool $confirm = false,
        public bool $back = false,
        public ?int $mouseX = null,
        public ?int $mouseY = null,
        public bool $click = false,
    ) {
        if (
            abs($vertical) > 1
            || abs($horizontal) > 1
            || ($mouseX === null) !== ($mouseY === null)
        ) {
            throw new InvalidArgumentException('Entrada de menu invalida.');
        }
    }
}
