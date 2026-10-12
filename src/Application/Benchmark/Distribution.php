<?php

declare(strict_types=1);

namespace Jogo\Application\Benchmark;

use InvalidArgumentException;

/** Percentis pelo posto mais próximo (ceil(p × n)); nenhuma amostra é descartada. */
final readonly class Distribution
{
    /** @param list<int|float> $samples */
    public function __construct(array $samples)
    {
        if ($samples === []) {
            throw new InvalidArgumentException('Forneça uma lista não vazia de amostras.');
        }
        foreach ($samples as $sample) {
            if (! is_finite((float) $sample) || $sample < 0) {
                throw new InvalidArgumentException('Amostras devem ser finitas e não negativas.');
            }
        }
        sort($samples, SORT_NUMERIC);
        $this->count = count($samples);
        $this->total = (float) array_sum($samples);
        if (! is_finite($this->total)) {
            throw new InvalidArgumentException('A soma das amostras deve ser finita.');
        }
        $this->mean = $this->total / $this->count;
        $middle = intdiv($this->count, 2);
        $this->median = $this->count % 2 === 0
            ? (float) ($samples[$middle - 1] / 2 + $samples[$middle] / 2) : (float) $samples[$middle];
        $this->p95 = (float) $samples[(int) ceil(0.95 * $this->count) - 1];
        $this->p99 = (float) $samples[(int) ceil(0.99 * $this->count) - 1];
        $this->maximum = (float) $samples[$this->count - 1];
    }

    public int $count;

    public float $total;

    public float $mean;

    public float $median;

    public float $p95;

    public float $p99;

    public float $maximum;

    public function meetsTimeBudget(): bool
    {
        return $this->p95 <= 16_600_000.0;
    }

    public function updatesPerSecond(): ?float
    {
        return $this->total === 0.0 ? null : $this->count * 1_000_000_000.0 / $this->total;
    }

    /** @return array<string, int|float> */
    public function values(float $divisor = 1.0): array
    {
        if (! is_finite($divisor) || $divisor <= 0.0) {
            throw new InvalidArgumentException('O divisor de unidades deve ser positivo e finito.');
        }

        return ['samples' => $this->count, 'total' => $this->total / $divisor, 'mean' => $this->mean / $divisor,
            'median' => $this->median / $divisor, 'p95' => $this->p95 / $divisor, 'p99' => $this->p99 / $divisor,
            'maximum' => $this->maximum / $divisor];
    }
}
