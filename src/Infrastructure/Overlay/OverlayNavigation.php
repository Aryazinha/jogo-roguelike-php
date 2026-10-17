<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Overlay;

use Jogo\Infrastructure\Menu\MenuInputEvent;

final readonly class OverlayNavigation
{
    public static function move(
        int $current,
        int $direction,
        int $count
    ): int {
        if ($count <= 0) {
            return 0;
        }

        return ($current + $direction + $count) % $count;
    }

    public static function clicked(
        MenuInputEvent $event,
        int $count
    ): ?int {
        if (
            ! $event->click
            || $event->mouseX === null
            || $event->mouseY === null
            || $event->mouseX < 115
            || $event->mouseX >= 685
        ) {
            return null;
        }

        for ($index = 0; $index < $count; $index++) {
            $y = 130 + $index * 79;

            if (
                $event->mouseY >= $y
                && $event->mouseY < $y + 71
            ) {
                return $index;
            }
        }

        return null;
    }
}
