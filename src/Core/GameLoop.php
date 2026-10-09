<?php

declare(strict_types=1);

namespace Jogo\Core;

use Closure;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Agenda atualizações fixas e uma renderização por chamada de tick().
 * Não conhece janela, entrada, entidades ou implementações dos sistemas.
 */
final class GameLoop
{
    public const STEP_SECONDS = 1.0 / 60.0;

    public const DEFAULT_MAX_UPDATES_PER_FRAME = 5;

    // Compensa arredondamentos na subtração de timestamps, sem mudar o passo.
    private const TIME_EPSILON = 1.0E-9;

    /** @var Closure(float): void */
    private readonly Closure $update;

    /** @var Closure(float): void */
    private readonly Closure $render;

    private float $lastTimeSeconds = 0.0;

    private float $accumulatedSeconds = 0.0;

    private int $completedSteps = 0;

    private bool $paused = false;

    /**
     * @param  callable(float): void  $update  Recebe sempre STEP_SECONDS.
     * @param  callable(float): void  $render  Recebe a fração para interpolação em [0, 1).
     */
    public function __construct(
        private readonly ClockInterface $clock,
        callable $update,
        callable $render,
        private readonly int $maxUpdatesPerFrame = self::DEFAULT_MAX_UPDATES_PER_FRAME,
    ) {
        if ($maxUpdatesPerFrame < 1) {
            throw new InvalidArgumentException('O limite de atualizações por quadro deve ser maior que zero.');
        }

        $this->update = Closure::fromCallable($update);
        $this->render = Closure::fromCallable($render);
        $this->lastTimeSeconds = $this->readTime();
    }

    /**
     * Processa um quadro e devolve a quantidade de atualizações realizadas.
     * O tempo acima do orçamento é descartado para não acumular atraso sem limite.
     *
     * @phpstan-impure
     */
    public function tick(): int
    {
        $now = $this->readTime();
        $deltaSeconds = $now - $this->lastTimeSeconds;
        $this->lastTimeSeconds = $now;
        $updates = 0;

        if (! $this->paused) {
            $budgetSeconds = self::STEP_SECONDS * $this->maxUpdatesPerFrame;
            $this->accumulatedSeconds += min($deltaSeconds, $budgetSeconds);

            while (! $this->paused
                && $updates < $this->maxUpdatesPerFrame
                && $this->accumulatedSeconds + self::TIME_EPSILON >= self::STEP_SECONDS
            ) {
                $this->accumulatedSeconds = max(0.0, $this->accumulatedSeconds - self::STEP_SECONDS);
                $this->completedSteps++;
                $updates++;
                ($this->update)(self::STEP_SECONDS);
            }
        }

        ($this->render)($this->accumulatedSeconds / self::STEP_SECONDS);

        return $updates;
    }

    /**
     * Conserva a fração de passo e elimina passos pendentes se a pausa vier de update().
     */
    public function pause(): void
    {
        if ($this->paused) {
            return;
        }

        $this->lastTimeSeconds = $this->readTime();
        $this->paused = true;
        $pendingSteps = floor(($this->accumulatedSeconds + self::TIME_EPSILON) / self::STEP_SECONDS);
        $this->accumulatedSeconds = max(0.0, $this->accumulatedSeconds - $pendingSteps * self::STEP_SECONDS);
    }

    /**
     * Reinicia a referência do relógio sem recuperar o tempo passado em pausa.
     */
    public function resume(): void
    {
        if (! $this->paused) {
            return;
        }

        $this->lastTimeSeconds = $this->readTime();
        $this->paused = false;
    }

    public function isPaused(): bool
    {
        return $this->paused;
    }

    /**
     * Tempo efetivamente simulado: pausas e atraso descartado não entram na conta.
     */
    public function elapsedSeconds(): float
    {
        return $this->completedSteps * self::STEP_SECONDS;
    }

    private function readTime(): float
    {
        $now = $this->clock->now();

        if (! is_finite($now) || $now < $this->lastTimeSeconds) {
            throw new UnexpectedValueException('O relógio deve fornecer segundos finitos, não negativos e sem retroceder.');
        }

        return $now;
    }
}
