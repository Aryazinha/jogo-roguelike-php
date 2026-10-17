<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Menu;

use Jogo\Infrastructure\Ffi\RaylibBinding;

final readonly class RaylibMenuScreenRenderer implements MenuScreenRendererInterface
{
    public function __construct(
        private RaylibBinding $raylib,
    ) {}

    /**
     * @param  list<string>  $options
     * @param  list<string>  $details
     */
    public function draw(
        string $title,
        array $options,
        int $selected,
        array $details = [],
    ): void {
        $this->raylib->clearBackground(23, 30, 38);

        $this->raylib->drawRectangle(
            0,
            0,
            800,
            9,
            85,
            180,
            100
        );

        $this->raylib->drawText(
            $title,
            65,
            36,
            32,
            245,
            245,
            245
        );

        $this->raylib->drawText(
            'Setas / WASD | ENTER: escolher | ESC: voltar',
            67,
            92,
            16,
            170,
            195,
            175
        );

        foreach ($options as $index => $option) {
            $x = 100;
            $y = 135 + $index * 56;

            $active = $index === $selected;

            $this->raylib->drawRectangle(
                $x,
                $y,
                600,
                46,
                $active ? 75 : 48,
                $active ? 145 : 62,
                $active ? 86 : 75
            );

            $this->raylib->drawText(
                ($active ? '> ' : '  ').$option,
                $x + 18,
                $y + 11,
                22,
                255,
                255,
                255
            );
        }

        $detailY = match (true) {
            count($options) >= 3 => 373,
            count($options) > 0 => 260,
            default => 145,
        };

        foreach ($details as $index => $detail) {
            $this->raylib->drawText(
                $detail,
                65,
                $detailY + $index * 25,
                17,
                225,
                225,
                225
            );
        }
    }
}
