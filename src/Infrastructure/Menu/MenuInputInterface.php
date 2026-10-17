<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Menu;

interface MenuInputInterface
{
    /** @phpstan-impure */
    public function poll(): MenuInputEvent;
}
