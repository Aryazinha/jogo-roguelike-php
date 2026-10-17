<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Feedback;

use Jogo\Domain\Value\Vector2;

final readonly class FeedbackLabel
{
    public function __construct(
        public string $text,
        public Vector2 $position,
        public int $red,
        public int $green,
        public int $blue,
    ) {}
}
