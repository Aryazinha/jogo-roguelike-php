<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Rendering;

use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Overlay\OverlaySnapshot;
use Jogo\Infrastructure\Play\PlayHudSnapshot;

final readonly class FrameSnapshot
{
    /**
     * @param  list<RenderItem>  $items
     * @param  list<string>  $hud
     */
    public function __construct(
        public Vector2 $previousFocus,
        public Vector2 $currentFocus,
        public array $items = [],
        public array $hud = [],
        public ?PlayHudSnapshot $playHud = null,
        public ?OverlaySnapshot $overlay = null,
    ) {}

    /** @return list<RenderItem> */
    public function orderedItems(): array
    {
        $ordered = [];

        foreach (RenderItem::LAYERS as $layer) {
            foreach ($this->items as $item) {
                if ($item->layer === $layer) {
                    $ordered[] = $item;
                }
            }
        }

        return $ordered;
    }
}
