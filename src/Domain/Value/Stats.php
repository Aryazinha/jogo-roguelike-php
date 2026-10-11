<?php

declare(strict_types=1);

namespace Jogo\Domain\Value;

use InvalidArgumentException;

/** Mapa de valores válidos; não cria componentes nem executa funcionalidades da B. */
final readonly class Stats
{
    public const ATTRIBUTES = ['dano', 'cooldown', 'alcance', 'projeteis', 'velocidadeProjetil',
        'anguloGraus', 'raioExplosao', 'atrasoDetonacaoSegundos', 'velocidadeJogador', 'vidaMaxima', 'raioColeta'];

    /** @var array<string, float> */
    private array $baseValues;

    /** @param array<string, mixed> $baseValues Os valores aceitos são números, convertidos para float. */
    public function __construct(array $baseValues)
    {
        $values = [];
        foreach ($baseValues as $attribute => $value) {
            self::assertAttribute($attribute);
            if ((! is_int($value) && ! is_float($value)) || ! is_finite((float) $value)) {
                throw new InvalidArgumentException("{$attribute} deve ser finito.");
            }
            $nonNegative = in_array($attribute, ['dano', 'atrasoDetonacaoSegundos', 'raioColeta'], true);
            if ($nonNegative ? $value < 0.0 : $value <= 0.0) {
                throw new InvalidArgumentException("{$attribute} está fora da faixa válida.");
            }
            if ($attribute === 'anguloGraus' && $value > 360.0) {
                throw new InvalidArgumentException('anguloGraus deve ser no máximo 360.');
            }
            if ($attribute === 'projeteis' && ($value < 1.0 || fmod($value, 1.0) !== 0.0 || $value >= (float) PHP_INT_MAX)) {
                throw new InvalidArgumentException('projeteis deve ser inteiro >= 1 e representável como int.');
            }
            $values[$attribute] = (float) $value;
        }
        $this->baseValues = $values;
    }

    public static function assertAttribute(string $attribute): void
    {
        if (! in_array($attribute, self::ATTRIBUTES, true)) {
            throw new InvalidArgumentException("Atributo desconhecido: {$attribute}.");
        }
    }

    public function has(string $attribute): bool
    {
        return array_key_exists($attribute, $this->baseValues);
    }

    public function value(string $attribute): float
    {
        return $this->baseValues[$attribute] ?? throw new InvalidArgumentException("Atributo ausente: {$attribute}.");
    }

    /** @return array<string, float> */
    public function values(): array
    {
        return $this->baseValues;
    }
}
