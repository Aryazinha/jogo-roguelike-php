<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Menu;

interface MenuScreenRendererInterface
{
    /**
     * @param  list<string>  $options
     * @param  list<string>  $details
     */
    public function draw(
        string $title,
        array $options,
        int $selected,
        array $details = [],
    ): void;
}
