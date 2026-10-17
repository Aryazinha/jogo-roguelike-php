<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Menu;

use Jogo\Infrastructure\Ffi\RaylibBinding;

final readonly class RaylibMenuInput implements MenuInputInterface
{
    public function __construct(
        private RaylibBinding $raylib,
    ) {}

    /** @phpstan-impure */
    public function poll(): MenuInputEvent
    {
        $up = $this->raylib->isKeyPressed(265)
            || $this->raylib->isKeyPressed(87);

        $down = $this->raylib->isKeyPressed(264)
            || $this->raylib->isKeyPressed(83);

        $left = $this->raylib->isKeyPressed(263)
            || $this->raylib->isKeyPressed(65);

        $right = $this->raylib->isKeyPressed(262)
            || $this->raylib->isKeyPressed(68);

        return new MenuInputEvent(
            vertical: (int) $down - (int) $up,
            horizontal: (int) $right - (int) $left,
            confirm: $this->raylib->isKeyPressed(257)
                || $this->raylib->isKeyPressed(32),
            back: $this->raylib->isKeyPressed(256),
            mouseX: $this->raylib->getMouseX(),
            mouseY: $this->raylib->getMouseY(),
            click: $this->raylib->isMouseButtonPressed(0),
        );
    }
}
