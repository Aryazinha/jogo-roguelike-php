<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Overlay;

use InvalidArgumentException;

final readonly class OverlaySnapshot
{
    /**
     * @param  list<OverlayOption>  $options
     */
    public function __construct(
        public string $title,
        public string $message,
        public array $options,
        public int $selected = 0,
        public string $footer = '',
    ) {
        if (trim($title) === '') {
            throw new InvalidArgumentException('Titulo do overlay invalido.');
        }

        if (
            $selected < 0
            || (
                $options !== []
                && $selected >= count($options)
            )
            || (
                $options === []
                && $selected !== 0
            )
        ) {
            throw new InvalidArgumentException('Selecao do overlay invalida.');
        }
    }
}
