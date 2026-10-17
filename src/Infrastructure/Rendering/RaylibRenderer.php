<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Rendering;

use Jogo\Infrastructure\Ffi\RaylibBinding;

final readonly class RaylibRenderer implements RendererInterface
{
    public function __construct(
        private RaylibBinding $raylib,
        private Camera $camera,
        private RaylibResourceCache $assets,
        private ?string $hudFontPath = null,
    ) {}

    public function render(FrameSnapshot $frame, float $alpha): void
    {
        $focus = Camera::interpolate(
            $frame->previousFocus,
            $frame->currentFocus,
            $alpha
        );

        $this->raylib->beginDrawing();

        try {
            $this->raylib->clearBackground(22, 26, 35);

            foreach ($frame->orderedItems() as $item) {
                $world = Camera::interpolate(
                    $item->previous,
                    $item->current,
                    $alpha
                );

                $screen = $this->camera->worldToScreen($world, $focus);

                if ($item->kind === 'texture' && $item->assetPath !== null) {
                    $this->raylib->drawTexture(
                        $this->assets->texture($item->assetPath),
                        (int) round($screen->x()),
                        (int) round($screen->y())
                    );

                    continue;
                }

                if ($item->kind === 'rectangle') {
                    $size = max(1, (int) round($item->size));

                    $this->raylib->drawRectangle(
                        (int) round($screen->x() - $size / 2),
                        (int) round($screen->y() - $size / 2),
                        $size,
                        $size,
                        $item->r,
                        $item->g,
                        $item->b
                    );

                    continue;
                }

                $this->raylib->drawCircle(
                    $screen->x(),
                    $screen->y(),
                    $item->size / 2.0,
                    $item->r,
                    $item->g,
                    $item->b
                );
            }

            foreach ($frame->hud as $index => $line) {
                $y = 15 + $index * 27;

                if ($this->hudFontPath !== null) {
                    $this->raylib->drawFontText(
                        $this->assets->font($this->hudFontPath),
                        $line,
                        15.0,
                        (float) $y,
                        20.0
                    );
                } else {
                    $this->raylib->drawText($line, 15, $y, 20);
                }
            }
        } finally {
            $this->raylib->endDrawing();
        }
    }
}
