<?php

declare(strict_types=1);

namespace Jogo\Domain\Value;

use InvalidArgumentException;

/**
 * Controla a vida atual e máxima de uma entidade que pode receber dano.
 *
 * Toda alteração passa por esta classe, garantindo que a vida permaneça entre
 * zero e o máximo definido.
 */
final class Health
{
    private float $current;

    public function __construct(
        private readonly float $maximum,
        ?float $current = null,
    ) {
        if (! is_finite($maximum) || $maximum <= 0.0) {
            throw new InvalidArgumentException('A vida máxima deve ser um número finito maior que zero.');
        }

        $initial = $current ?? $maximum;

        if (! is_finite($initial) || $initial < 0.0 || $initial > $maximum) {
            throw new InvalidArgumentException('A vida atual deve estar entre zero e a vida máxima.');
        }

        $this->current = $initial;
    }

    public function current(): float
    {
        return $this->current;
    }

    public function maximum(): float
    {
        return $this->maximum;
    }

    public function ratio(): float
    {
        return $this->current / $this->maximum;
    }

    public function isDead(): bool
    {
        return $this->current <= 0.0;
    }

    /**
     * Aplica dano e devolve quanto foi realmente retirado da vida.
     */
    public function damage(float $amount): float
    {
        self::assertNonNegativeFinite($amount, 'O dano');

        $applied = min($amount, $this->current);
        $this->current -= $applied;

        return $applied;
    }

    /**
     * Recupera vida e devolve quanto foi realmente restaurado.
     */
    public function heal(float $amount): float
    {
        self::assertNonNegativeFinite($amount, 'A cura');

        $missing = $this->maximum - $this->current;
        $restored = min($amount, $missing);
        $this->current += $restored;

        return $restored;
    }

    private static function assertNonNegativeFinite(float $amount, string $subject): void
    {
        if (! is_finite($amount) || $amount < 0.0) {
            throw new InvalidArgumentException("{$subject} deve ser um número finito maior ou igual a zero.");
        }
    }
}
