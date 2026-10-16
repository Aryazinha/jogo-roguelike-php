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
 * Cada vizinho empurra na direção oposta a ele com força 1 - d/raio: vale 1
 * quando está colado e cai até 0 na borda do raio. A soma é limitada a
 * comprimento 1, mas não é esticada até 1: no meio de um grupo os empurrões
 * se anulam e o inimigo quase não sai do lugar, em vez de disparar para um
 * lado qualquer. A consulta usa a grade espacial da A.
 */
final readonly class SeparationBehavior implements BehaviorStrategy
{
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
            $push = $push->add($direction->scale(max(0.0, 1.0 - $distance / $this->radius)));
        }

        return $push->length() > 1.0 ? $push->normalized() : $push;
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
