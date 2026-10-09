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

        $this->remainingSeconds = max(0.0, $this->remainingSeconds - $deltaSeconds);
    }

    /**
     * Torna o cooldown imediatamente disponível.
     */
    public function reset(): void
    {
        $this->remainingSeconds = 0.0;
    }
}
