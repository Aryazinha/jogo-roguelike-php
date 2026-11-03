<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Rendering;

final readonly class NullRenderer implements RendererInterface
{
    public function render(FrameSnapshot $frame, float $alpha): void
    {
        Camera::interpolate(
            $frame->previousFocus,
            $frame->currentFocus,
            $alpha
        );
    }
}
