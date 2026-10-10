<?php

declare(strict_types=1);

namespace Jogo\Domain\Value;

use InvalidArgumentException;

/**
 * Controla o intervalo individual entre usos de uma habilidade ou arma.
 *
 * Uma instância nova começa pronta. O método tryStart() concentra a verificação
 * e o início do cooldown em uma única operação, evitando usos duplicados.
 */
final class Cooldown
{
    private float $remainingSeconds = 0.0;

    private float $elapsedSeconds = 0.0;

    private float $timeCorrection = 0.0;

    public function __construct(private readonly float $durationSeconds)
    {
        if (! is_finite($durationSeconds) || $durationSeconds <= 0.0) {
            throw new InvalidArgumentException('A duração do cooldown deve ser um número finito maior que zero.');
        }
    }

    public function durationSeconds(): float
    {
        return $this->durationSeconds;
    }

    public function remainingSeconds(): float
    {
        return $this->remainingSeconds;
    }

    public function isReady(): bool
    {
        return $this->remainingSeconds <= 0.0;
    }

    /**
     * Inicia o cooldown somente se ele estiver disponível.
     *
     * @return bool true quando o uso foi aceito; false quando ainda estava em cooldown
     */
    public function tryStart(): bool
    {
        if (! $this->isReady()) {
            return false;
        }

        $this->remainingSeconds = $this->durationSeconds;
        $this->elapsedSeconds = 0.0;
        $this->timeCorrection = 0.0;

        return true;
    }

    /**
     * Avança o relógio do cooldown sem permitir valores negativos.
     */
    public function advance(float $deltaSeconds): void
    {
        if (! is_finite($deltaSeconds) || $deltaSeconds < 0.0) {
            throw new InvalidArgumentException('O intervalo deve ser um número finito maior ou igual a zero.');
        }

        if ($deltaSeconds === 0.0 || $this->isReady()) {
            return;
        }

        if ($deltaSeconds >= $this->remainingSeconds) {
            $this->remainingSeconds = 0.0;
            $this->elapsedSeconds = $this->durationSeconds;
            $this->timeCorrection = 0.0;

            return;
        }

        // Soma compensada (Kahan): 120 passos de 1/60 s não deixam um resíduo após 2 s.
        // Não usa tolerância absoluta que encurtaria cooldowns muito pequenos.
        $adjusted = $deltaSeconds - $this->timeCorrection;
        $next = $this->elapsedSeconds + $adjusted;
        $this->timeCorrection = ($next - $this->elapsedSeconds) - $adjusted;
        $this->elapsedSeconds = $next;
        $this->remainingSeconds = max(0.0, $this->durationSeconds - $next);
    }

    /**
     * Torna o cooldown imediatamente disponível.
     */
    public function reset(): void
    {
        $this->remainingSeconds = 0.0;
        $this->elapsedSeconds = 0.0;
        $this->timeCorrection = 0.0;
    }
}
