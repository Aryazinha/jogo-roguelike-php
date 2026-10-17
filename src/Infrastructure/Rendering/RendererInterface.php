<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Rendering;

interface RendererInterface
{
    public function render(FrameSnapshot $frame, float $alpha): void;
}
