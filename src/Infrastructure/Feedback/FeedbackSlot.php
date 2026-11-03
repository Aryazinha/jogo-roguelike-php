<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Feedback;

use Jogo\Domain\Value\Vector2;

/** Slot reutilizavel para limitar alocacoes de efeitos. */
final class FeedbackSlot
{
    public bool $active = false;

    public string $kind = '';

    public float $startedAt = 0.0;

    public float $duration = 0.0;

    public ?float $damage = null;

    public Vector2 $position;

    public function __construct()
    {
        $this->position = Vector2::zero();
    }

    public function activate(
        string $kind,
        Vector2 $position,
        float $startedAt,
        float $duration,
        ?float $damage,
    ): void {
        $this->active = true;
        $this->kind = $kind;
        $this->position = $position;
        $this->startedAt = $startedAt;
        $this->duration = $duration;
        $this->damage = $damage;
    }

    public function expire(): void
    {
        $this->active = false;
        $this->damage = null;
    }
}
