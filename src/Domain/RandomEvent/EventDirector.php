<?php

declare(strict_types=1);

namespace Jogo\Domain\RandomEvent;

use Jogo\Domain\Random\RandomIndex;
use Jogo\Domain\Value\CombatValidation;

/**
 * Sorteia e controla os eventos aleatórios da partida (RF35).
 *
 * Um evento por vez. Depois que um termina, espera entre 60 e 120 s (sorteado)
 * e começa outro, escolhido pelo peso: Horda e Chuva de XP saem duas vezes
 * mais que o Tesouro. Enquanto um evento dura, os multiplicadores abaixo
 * mudam; quando acaba, voltam a 1, sem deixar resto.
 *
 * Quem aplica os multiplicadores: o WaveDirector (Horda), o ChestSystem
 * (Tesouro) e a Frente B, que concede o XP (Chuva de XP).
 */
final class EventDirector
{
    private ?RandomEventDefinition $active = null;

    private float $endsAt = 0.0;

    private float $nextAt;

    public function __construct(private readonly EventSettings $settings, private readonly RandomIndex $random)
    {
        $this->nextAt = $this->gap();
    }

    /** Usa o tempo da partida: pausado, ele não anda e o evento não acaba. */
    public function update(float $elapsedSeconds): void
    {
        CombatValidation::nonNegative($elapsedSeconds, 'elapsedSeconds');

        if ($this->active !== null && $elapsedSeconds >= $this->endsAt) {
            $this->nextAt = $this->endsAt + $this->gap();
            $this->active = null;
        }
        if ($this->active === null && $elapsedSeconds >= $this->nextAt) {
            $this->active = $this->draw();
            // Começa no instante marcado, não no passo em que foi notado, para a duração ser exata.
            $this->endsAt = $this->nextAt + $this->active->durationSeconds;
        }
    }

    public function active(): ?RandomEventDefinition
    {
        return $this->active;
    }

    /** Quando o próximo evento começa (se nenhum está ativo). */
    public function nextStartsAt(): float
    {
        return $this->nextAt;
    }

    public function activeEndsAt(): ?float
    {
        return $this->active === null ? null : $this->endsAt;
    }

    /** Horda: inimigos por spawn × 3. */
    public function spawnCountMultiplier(): float
    {
        return $this->multiplier(EventEffect::SPAWN_COUNT);
    }

    /** Horda: intervalo entre spawns × 0,5. */
    public function spawnIntervalMultiplier(): float
    {
        return $this->multiplier(EventEffect::SPAWN_INTERVAL);
    }

    /** Chuva de XP: XP de cada inimigo × 2, aplicado pela Frente B. */
    public function xpMultiplier(): float
    {
        return $this->multiplier(EventEffect::XP_DROP);
    }

    /** Tesouro: chance de baú × 2,5. */
    public function chestChanceMultiplier(): float
    {
        return $this->multiplier(EventEffect::CHEST_CHANCE);
    }

    private function multiplier(string $attribute): float
    {
        $percent = 0.0;
        foreach ($this->active->effects ?? [] as $effect) {
            if ($effect->attribute === $attribute) {
                $percent += $effect->percent;
            }
        }

        return 1.0 + $percent;
    }

    private function draw(): RandomEventDefinition
    {
        $roll = $this->random->nextIndex($this->settings->totalWeight());
        foreach ($this->settings->events as $event) {
            if ($roll < $event->weight) {
                return $event;
            }
            $roll -= $event->weight;
        }

        // Inalcançável: o sorteio é menor que a soma dos pesos.
        return $this->settings->events[count($this->settings->events) - 1];
    }

    private function gap(): float
    {
        $min = $this->settings->minGapSeconds;

        return (float) ($min + $this->random->nextIndex($this->settings->maxGapSeconds - $min + 1));
    }
}
