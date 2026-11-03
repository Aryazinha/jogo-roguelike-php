<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Feedback;

use Jogo\Infrastructure\Rendering\RenderItem;

final readonly class FeedbackFrame
{
    /**
     * @param  list<RenderItem>  $items
     * @param  list<FeedbackLabel>  $labels
     */
    public function __construct(
        public array $items,
        public array $labels,
    ) {}
}
