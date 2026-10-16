<?php

declare(strict_types=1);

namespace Jogo\Domain\System;

use InvalidArgumentException;
use Jogo\Domain\Ai\Behavior\BehaviorStrategy;
use Jogo\Domain\Ai\EntityView;
use Jogo\Domain\Ai\SpatialWorldView;
use Jogo\Domain\Enemy\Enemy;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\InputState;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;

/**
 * Move os inimigos um passo fixo de acordo com o comportamento de cada tipo.
 *
 * Trabalha em duas fases: primeiro todos decidem a direção olhando o mesmo
 * retrato do mundo, depois todos andam. Assim a ordem da lista não muda o
 * resultado, e a mesma seed sempre produz as mesmas posições.
 */
final readonly class EnemySteeringSystem
{
    /** @param array<string, BehaviorStrategy> $behaviors Estratégia por ID de comportamento do arquétipo. */
    public function __construct(private MovementSystem $movement, private array $behaviors)
    {
        foreach (array_keys($behaviors) as $id) {
            CombatValidation::requiredId((string) $id, 'behaviorId');
        }
    }

    public function update(GameWorld $world, SpatialWorldView $view, float $deltaSeconds): void
    {
        CombatValidation::nonNegative($deltaSeconds, 'deltaSeconds');
        // Pausa: o loop da A não avança o tempo, então nada se move.
        if ($deltaSeconds === 0.0) {
            return;
        }

        $view->refresh();
        $plans = [];
        foreach ($world->enemies() as $enemy) {
            if (! $enemy->isActive() || $enemy->health()->isDead()) {
                continue;
            }
            $plans[] = [$enemy, $this->behaviorFor($enemy)->direction(self::snapshot($enemy), $view, $deltaSeconds)];
        }

        foreach ($plans as [$enemy, $direction]) {
            $facing = $direction->length() > 0.0 ? $direction->normalized() : null;
            $this->movement->update($enemy, $enemy->facing(), new InputState($direction, $facing),
                $enemy->speed(), $deltaSeconds, true);
        }

        // As posições mudaram: quem consultar depois (armas, contato) precisa reconstruir.
        $view->invalidate();
    }

    private function behaviorFor(Enemy $enemy): BehaviorStrategy
    {
        $id = $enemy->archetype()->behaviorId;

        return $this->behaviors[$id]
            ?? throw new InvalidArgumentException("Nenhuma estratégia registrada para o comportamento {$id}.");
    }

    private static function snapshot(Enemy $enemy): EntityView
    {
        $position = $enemy->position();

        return new EntityView($enemy->id(), new Vector2($position->x(), $position->y()),
            $enemy->collisionRadius(), 'enemy', true);
    }
}
