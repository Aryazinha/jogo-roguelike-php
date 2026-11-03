<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Overlay;

use InvalidArgumentException;

final readonly class OverlayOption
{
    public function __construct(
        public string $title,
        public string $description = '',
        public string $effect = '',
    ) {
        if (trim($title) === '') {
            throw new InvalidArgumentException('Titulo da opcao invalido.');
        }
    }
}
