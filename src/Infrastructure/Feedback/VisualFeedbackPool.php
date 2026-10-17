<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Feedback;

use InvalidArgumentException;
use Jogo\Core\ClockInterface;
use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Rendering\RenderItem;

final class VisualFeedbackPool
{
    /** @var list<FeedbackSlot> */
    private array $slots = [];

    private int $nextIndex = 0;

    public function __construct(
        private readonly ClockInterface $clock,
        int $capacity = 96,
    ) {
        if ($capacity < 1) {
            throw new InvalidArgumentException(
                'Capacidade de efeitos invalida.'
            );
        }

        for ($i = 0; $i < $capacity; $i++) {
            $this->slots[] = new FeedbackSlot;
        }
    }

    /** @phpstan-impure */
    public function emit(
        string $kind,
        Vector2 $position,
        ?float $damage = null,
    ): void {
        if (! in_array($kind, FeedbackTypes::ALL, true)) {
            throw new InvalidArgumentException(
                'Tipo de feedback invalido.'
            );
        }

        if (
            $damage !== null
            && (! is_finite($damage) || $damage <= 0.0)
        ) {
            throw new InvalidArgumentException(
                'Dano visual invalido.'
            );
        }

        $now = $this->clock->now();
        $capacity = count($this->slots);
        $index = $this->nextIndex;

        for ($attempt = 0; $attempt < $capacity; $attempt++) {
            $candidate = ($this->nextIndex + $attempt) % $capacity;
            $slot = $this->slots[$candidate];

            if (
                ! $slot->active
                || $now - $slot->startedAt >= $slot->duration
            ) {
                $index = $candidate;
                break;
            }
        }

        $duration = match ($kind) {
            'player_died' => 1.4,
            'level_up' => 1.2,
            'enemy_killed', 'chest_opened' => 1.0,
            default => 0.8,
        };

        $this->slots[$index]->activate(
            $kind,
            $position,
            $now,
            $duration,
            $damage
        );

        $this->nextIndex = ($index + 1) % $capacity;
    }

    /** @phpstan-impure */
    public function frame(): FeedbackFrame
    {
        $now = $this->clock->now();
        $items = [];
        $labels = [];

        foreach ($this->slots as $slot) {
            if (! $slot->active) {
                continue;
            }

            $age = max(0.0, $now - $slot->startedAt);

            if ($age >= $slot->duration) {
                $slot->expire();

                continue;
            }

            $progress = min(1.0, $age / $slot->duration);
            $fade = max(0.15, 1.0 - $progress);

            [$red, $green, $blue] = FeedbackTypes::color(
                $slot->kind
            );

            $r = max(1, (int) round($red * $fade));
            $g = max(1, (int) round($green * $fade));
            $b = max(1, (int) round($blue * $fade));

            // Flash central que aumenta e perde intensidade.
            $items[] = new RenderItem(
                'effects',
                'circle',
                $slot->position,
                $slot->position,
                18.0 + 36.0 * $progress,
                $r,
                $g,
                $b
            );

            // Quatro particulas se afastam do ponto do evento.
            for ($i = 0; $i < 4; $i++) {
                $angle = $i * pi() / 2.0 + pi() / 4.0;
                $distance = 10.0 + 38.0 * $progress;

                $position = $slot->position->add(
                    new Vector2(
                        cos($angle) * $distance,
                        sin($angle) * $distance
                    )
                );

                $items[] = new RenderItem(
                    'effects',
                    'circle',
                    $position,
                    $position,
                    max(3.0, 10.0 * $fade),
                    $r,
                    $g,
                    $b
                );
            }

            $text = $slot->damage === null
                ? FeedbackTypes::label($slot->kind)
                : sprintf('-%.0f', $slot->damage);

            $labels[] = new FeedbackLabel(
                $text,
                $slot->position->add(
                    new Vector2(0.0, -34.0 - 23.0 * $progress)
                ),
                $r,
                $g,
                $b
            );
        }

        return new FeedbackFrame($items, $labels);
    }

    public function activeCount(): int
    {
        $now = $this->clock->now();
        $count = 0;

        foreach ($this->slots as $slot) {
            if (
                $slot->active
                && $now - $slot->startedAt < $slot->duration
            ) {
                $count++;
            }
        }

        return $count;
    }

    public function capacity(): int
    {
        return count($this->slots);
    }

    public function clear(): void
    {
        foreach ($this->slots as $slot) {
            $slot->expire();
        }
    }
}
