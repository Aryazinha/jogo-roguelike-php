<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Rendering;

use Jogo\Infrastructure\Assets\AssetCatalog;
use Jogo\Infrastructure\Ffi\RaylibBinding;
use Jogo\Infrastructure\Overlay\OverlaySnapshot;
use Jogo\Infrastructure\Play\PlayHudSnapshot;

final readonly class RaylibRenderer implements RendererInterface
{
    public function __construct(
        private RaylibBinding $raylib,
        private Camera $camera,
        private RaylibResourceCache $assets,
        private ?string $hudFontPath = null,
        private ?AssetCatalog $catalog = null,
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
                    $this->raylib->drawTextureCentered(
                        $this->assets->texture($item->assetPath),
                        $screen->x(),
                        $screen->y(),
                        $item->size
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

            foreach ($frame->feedbackLabels as $label) {
                $screen = $this->camera->worldToScreen(
                    $label->position,
                    $focus
                );

                $this->raylib->drawText(
                    $label->text,
                    (int) round($screen->x()),
                    (int) round($screen->y()),
                    18,
                    $label->red,
                    $label->green,
                    $label->blue
                );
            }

            if ($frame->playHud !== null) {
                $this->drawPlayHud($frame->playHud);
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

            if ($frame->overlay !== null) {
                $this->drawOverlay($frame->overlay);
            }
        } finally {
            $this->raylib->endDrawing();
        }
    }

    private function drawPlayHud(PlayHudSnapshot $hud): void
    {
        $this->raylib->drawRectangle(8, 8, 305, 138, 19, 24, 36);

        if ($this->catalog !== null) {
            $this->raylib->drawTextureCentered(
                $this->assets->texture(
                    $this->catalog->sprite('hud_heart')
                ),
                294.0,
                29.0,
                22.0
            );

            $this->raylib->drawTextureCentered(
                $this->assets->texture(
                    $this->catalog->sprite('hud_xp')
                ),
                294.0,
                77.0,
                22.0
            );
        }

        $this->raylib->drawText(
            sprintf('VIDA %.0f / %.0f', $hud->health, $hud->maxHealth),
            18,
            16,
            18
        );

        $this->drawBar(
            18,
            42,
            265,
            12,
            $hud->healthRatio(),
            55,
            205,
            85
        );

        $this->raylib->drawText(
            sprintf('XP %d / %d', $hud->xp, $hud->xpToNextLevel),
            18,
            64,
            18
        );

        $this->drawBar(
            18,
            90,
            265,
            10,
            $hud->xpRatio(),
            65,
            155,
            240
        );

        $this->raylib->drawText(
            'NIVEL '.$hud->level.'    TEMPO '.$hud->formattedTime(),
            18,
            112,
            18
        );

        $this->raylib->drawRectangle(
            8,
            322,
            540,
            119,
            19,
            24,
            36
        );

        $this->raylib->drawText('ARMAS:', 18, 330, 17);

        foreach ($hud->weaponLines() as $index => $line) {
            if ($index >= 2) {
                break;
            }

            $this->raylib->drawText(
                $line,
                18,
                352 + $index * 21,
                16
            );
        }

        $passives = $hud->passives === []
            ? 'Nenhum'
            : implode(', ', $hud->passives);

        $this->raylib->drawText(
            'PASSIVOS: '.mb_strimwidth($passives, 0, 46, '...'),
            18,
            406,
            16
        );

        if ($hud->wave !== null) {
            $this->raylib->drawText(
                $hud->wave,
                350,
                100,
                18
            );
        }

        if ($hud->boss !== null) {
            $this->raylib->drawRectangle(
                328,
                8,
                462,
                79,
                19,
                24,
                36
            );

            $this->raylib->drawText(
                'BOSS: '.$hud->boss->name,
                340,
                19,
                20
            );

            $this->drawBar(
                340,
                52,
                435,
                17,
                $hud->boss->healthRatio(),
                210,
                50,
                55
            );
        }
    }

    private function drawOverlay(OverlaySnapshot $overlay): void
    {
        $this->raylib->drawRectangle(
            86,
            29,
            628,
            391,
            18,
            27,
            38
        );

        $this->raylib->drawRectangle(
            86,
            29,
            628,
            6,
            80,
            185,
            105
        );

        $this->raylib->drawText(
            mb_strimwidth($overlay->title, 0, 29, '...'),
            115,
            50,
            29
        );

        $this->raylib->drawText(
            mb_strimwidth($overlay->message, 0, 65, '...'),
            115,
            96,
            16,
            210,
            220,
            210
        );

        foreach ($overlay->options as $index => $option) {
            if ($index >= 3) {
                break;
            }

            $y = 130 + $index * 79;
            $active = $index === $overlay->selected;

            $this->raylib->drawRectangle(
                115,
                $y,
                570,
                71,
                $active ? 67 : 40,
                $active ? 131 : 55,
                $active ? 84 : 72
            );

            $this->raylib->drawText(
                ($active ? '> ' : '  ')
                    .mb_strimwidth($option->title, 0, 38, '...'),
                128,
                $y + 7,
                19
            );

            if ($option->description !== '') {
                $this->raylib->drawText(
                    mb_strimwidth($option->description, 0, 67, '...'),
                    129,
                    $y + 31,
                    15
                );
            }

            if ($option->effect !== '') {
                $this->raylib->drawText(
                    mb_strimwidth($option->effect, 0, 67, '...'),
                    129,
                    $y + 52,
                    14,
                    205,
                    226,
                    155
                );
            }
        }

        $this->raylib->drawText(
            mb_strimwidth($overlay->footer, 0, 74, '...'),
            115,
            393,
            15,
            190,
            205,
            190
        );
    }

    private function drawBar(
        int $x,
        int $y,
        int $width,
        int $height,
        float $ratio,
        int $r,
        int $g,
        int $b,
    ): void {
        $this->raylib->drawRectangle(
            $x,
            $y,
            $width,
            $height,
            65,
            70,
            80
        );

        $filled = (int) round(
            $width * max(0.0, min(1.0, $ratio))
        );

        if ($filled > 0) {
            $this->raylib->drawRectangle(
                $x,
                $y,
                $filled,
                $height,
                $r,
                $g,
                $b
            );
        }
    }
}
