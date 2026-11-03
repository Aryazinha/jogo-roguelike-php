<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Play;

use Jogo\Infrastructure\Rendering\RendererInterface;

final readonly class PlayFramePresenter
{
    public function __construct(
        private PlaySnapshotProviderInterface $provider,
        private PlayFrameFactory $factory,
        private RendererInterface $renderer,
    ) {}

    /** @phpstan-impure */
    public function render(float $alpha): void
    {
        $snapshot = $this->provider->snapshot();

        $this->renderer->render(
            $this->factory->create($snapshot),
            $alpha
        );
    }
}
