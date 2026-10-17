<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Overlay;

use Jogo\Infrastructure\Play\PlayFrameFactory;
use Jogo\Infrastructure\Play\PlaySnapshotProviderInterface;
use Jogo\Infrastructure\Rendering\FrameSnapshot;
use Jogo\Infrastructure\Rendering\RendererInterface;

final readonly class OverlayFramePresenter
{
    public function __construct(
        private PlaySnapshotProviderInterface $provider,
        private PlayFrameFactory $factory,
        private RendererInterface $renderer,
    ) {}

    /** @phpstan-impure */
    public function render(OverlaySnapshot $overlay, float $alpha): void
    {
        $snapshot = $this->provider->snapshot();
        $frame = $this->factory->create($snapshot);

        $this->renderer->render(
            new FrameSnapshot(
                $frame->previousFocus,
                $frame->currentFocus,
                $frame->items,
                $frame->hud,
                $frame->playHud,
                $overlay,
            ),
            $alpha
        );
    }
}
