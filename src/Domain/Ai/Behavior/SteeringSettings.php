<?php

declare(strict_types=1);

namespace Jogo\Domain\Ai\Behavior;

use Jogo\Domain\Value\CombatValidation;

/** Pesos e raio da perseguição com separação, lidos de enemies.json. */
final readonly class SteeringSettings
{
    public function __construct(
        public float $separationRadius,
        public float $seekWeight,
        public float $separationWeight,
    ) {
        CombatValidation::positive($separationRadius, 'separationRadius');
        CombatValidation::nonNegative($seekWeight, 'seekWeight');
        CombatValidation::nonNegative($separationWeight, 'separationWeight');
    }

    /** Comportamento "perseguir" dos inimigos comuns: seek + separation. */
    public function chase(): BehaviorStrategy
    {
        return new WeightedBehavior([
            [new SeekBehavior, $this->seekWeight],
            [new SeparationBehavior($this->separationRadius), $this->separationWeight],
        ]);
    }
}
