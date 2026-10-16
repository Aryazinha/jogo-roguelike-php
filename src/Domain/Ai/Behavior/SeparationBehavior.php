<?php

declare(strict_types=1);

namespace Jogo\Domain\Ai\Behavior;

use Jogo\Domain\Ai\EntityView;
use Jogo\Domain\Ai\WorldView;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Vector2;

/**
 * Separation: afastar-se dos outros inimigos que estão perto demais (PLANO.md §4.1).
 *
 * Cada vizinho empurra na direção oposta a ele com força 1/d, então quem
 * está colado empurra muito e quem está longe quase nada. A consulta usa
 * a grade espacial da A, sem comparar todos contra todos.
 */
final readonly class SeparationBehavior implements BehaviorStrategy
{
    /** Abaixo de 1 px a força ficaria enorme; trata como se estivesse a 1 px. */
    private const MIN_DISTANCE = 1.0;

    public function __construct(private float $radius)
    {
        CombatValidation::positive($radius, 'radius');
    }

    public function direction(EntityView $enemy, WorldView $world, float $deltaSeconds): Vector2
    {
        $push = Vector2::zero();

        foreach ($world->neighbors($enemy->position, $this->radius) as $other) {
            if ($other->id === $enemy->id || $other->kind !== 'enemy' || $other->isAlive === false) {
                continue;
            }
            $away = $enemy->position->subtract($other->position);
            $distance = $away->length();
            $direction = $distance > 0.0 ? $away->scale(1.0 / $distance) : self::tieBreak($enemy->id, $other->id);
            $push = $push->add($direction->scale(1.0 / max($distance, self::MIN_DISTANCE)));
        }

        return $push->normalized();
    }

    /**
     * Dois inimigos exatamente no mesmo ponto não têm direção para fugir.
     * O ângulo sai dos IDs, então o resultado é sempre o mesmo e cada um
     * vai para um lado: o par (a, b) gera o ângulo oposto ao par (b, a).
     */
    private static function tieBreak(string $self, string $other): Vector2
    {
        [$first, $second] = $self < $other ? [$self, $other] : [$other, $self];
        $angle = deg2rad(crc32($first.'|'.$second) % 360);
        $direction = new Vector2(cos($angle), sin($angle));

        return $self < $other ? $direction : $direction->scale(-1.0);
    }
}
