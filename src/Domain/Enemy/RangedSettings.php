<?php

declare(strict_types=1);

namespace Jogo\Domain\Enemy;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;

/**
 * Parâmetros do comportamento manter_distancia (Skeleton, RF36).
 *
 * A histerese cria uma faixa morta em volta da distância ideal para o
 * inimigo não ficar alternando entre avançar e recuar a cada passo.
 */
final readonly class RangedSettings
{
    public function __construct(
        public float $idealDistance,
        public float $hysteresis,
        public float $shotCooldownSeconds,
    ) {
        CombatValidation::positive($idealDistance, 'idealDistance');
        CombatValidation::nonNegative($hysteresis, 'hysteresis');
        CombatValidation::positive($shotCooldownSeconds, 'shotCooldownSeconds');
        if ($hysteresis >= $idealDistance) {
            throw new InvalidArgumentException('hysteresis deve ser menor que idealDistance.');
        }
    }

    public function minDistance(): float
    {
        return $this->idealDistance - $this->hysteresis;
    }

    public function maxDistance(): float
    {
        return $this->idealDistance + $this->hysteresis;
    }
}
