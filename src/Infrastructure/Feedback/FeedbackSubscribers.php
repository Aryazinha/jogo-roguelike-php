<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Feedback;

use Jogo\Domain\Event\EventBusInterface;
use Jogo\Infrastructure\Audio\AudioEventSubscriber;

final readonly class FeedbackSubscribers
{
    public function __construct(
        private AudioEventSubscriber $audio,
        private VisualFeedbackSubscriber $visual,
    ) {}

    public function register(EventBusInterface $events): void
    {
        $this->audio->register($events);
        $this->visual->register($events);
    }
}
