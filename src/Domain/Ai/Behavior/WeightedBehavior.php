<?php

declare(strict_types=1);

namespace Jogo\Domain\Ai\Behavior;

use InvalidArgumentException;
use Jogo\Domain\Ai\EntityView;
use Jogo\Domain\Ai\WorldView;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Vector2;

/**
 * Soma várias vontades com pesos, como peso_seek × seek + peso_sep × separation.
 *
 * O resultado também fica com comprimento até 1, então o inimigo nunca
 * passa da velocidade do arquétipo, por mais vontades que sejam somadas.
 */
final readonly class WeightedBehavior implements BehaviorStrategy
{
    /** @var list<array{BehaviorStrategy, float}> */
    private array $parts;

    /** @param list<array{BehaviorStrategy, float}> $parts Pares (comportamento, peso). */
    public function __construct(array $parts)
    {
        if ($parts === []) {
            throw new InvalidArgumentException('WeightedBehavior precisa de pelo menos um comportamento.');
        }
        foreach ($parts as [, $weight]) {
            CombatValidation::nonNegative($weight, 'weight');
        }
        $this->parts = $parts;
    }

    public function direction(EntityView $enemy, WorldView $world, float $deltaSeconds): Vector2
    {
        $sum = Vector2::zero();
        foreach ($this->parts as [$behavior, $weight]) {
            if ($weight > 0.0) {
                $sum = $sum->add($behavior->direction($enemy, $world, $deltaSeconds)->scale($weight));
            }
        }

        return $sum->length() > 1.0 ? $sum->normalized() : $sum;
    }
}
