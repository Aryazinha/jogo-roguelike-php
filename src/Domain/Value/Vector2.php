<?php

declare(strict_types=1);

namespace Jogo\Domain\Value;

use InvalidArgumentException;

/**
 * Representa uma posição, direção ou deslocamento no mundo do jogo.
 *
 * O objeto é imutável: cada operação devolve um novo vetor. Isso evita que uma
 * entidade altere acidentalmente a posição de outra ao compartilhar referências.
 */
final readonly class Vector2
{
    private const DEFAULT_EPSILON = 1.0E-9;

    public function __construct(
        private float $x,
        private float $y,
    ) {
        self::assertFinite($x, 'x');
        self::assertFinite($y, 'y');
    }

    public static function zero(): self
    {
        return new self(0.0, 0.0);
    }

    public function x(): float
    {
        return $this->x;
    }

    public function y(): float
    {
        return $this->y;
    }

    public function add(self $other): self
    {
        return new self($this->x + $other->x, $this->y + $other->y);
    }

    public function subtract(self $other): self
    {
        return new self($this->x - $other->x, $this->y - $other->y);
    }

    public function scale(float $factor): self
    {
        self::assertFinite($factor, 'factor');

        return new self($this->x * $factor, $this->y * $factor);
    }

    public function lengthSquared(): float
    {
        return ($this->x * $this->x) + ($this->y * $this->y);
    }

    public function length(): float
    {
        // hypot() evita overflow intermediário ao elevar coordenadas grandes ao quadrado.
        return hypot($this->x, $this->y);
    }

    public function distanceTo(self $other): float
    {
        return $this->subtract($other)->length();
    }

    /**
     * Devolve um vetor de comprimento 1 na mesma direção.
     *
     * Vetores praticamente nulos não possuem direção útil. Nesses casos,
     * devolvemos o vetor zero em vez de dividir por um número muito pequeno.
     */
    public function normalized(float $epsilon = self::DEFAULT_EPSILON): self
    {
        self::assertValidEpsilon($epsilon);

        $length = $this->length();

        if ($length <= $epsilon) {
            return self::zero();
        }

        return $this->scale(1.0 / $length);
    }

    /**
     * Compara números de ponto flutuante usando uma tolerância explícita.
     */
    public function equals(self $other, float $epsilon = self::DEFAULT_EPSILON): bool
    {
        self::assertValidEpsilon($epsilon);

        return abs($this->x - $other->x) <= $epsilon
            && abs($this->y - $other->y) <= $epsilon;
    }

    private static function assertFinite(float $value, string $name): void
    {
        if (! is_finite($value)) {
            throw new InvalidArgumentException("O componente {$name} deve ser um número finito.");
        }
    }

    private static function assertValidEpsilon(float $epsilon): void
    {
        if (! is_finite($epsilon) || $epsilon < 0.0) {
            throw new InvalidArgumentException('A tolerância deve ser um número finito maior ou igual a zero.');
        }
    }
}
