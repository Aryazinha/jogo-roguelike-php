<?php

declare(strict_types=1);

namespace Jogo\Domain\System;

use InvalidArgumentException;
use Jogo\Domain\Ai\Behavior\BehaviorStrategy;
use Jogo\Domain\Ai\CrowdWorldView;
use Jogo\Domain\Enemy\Enemy;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\InputState;
use Jogo\Domain\World\GameWorld;

/**
 * Move os inimigos um passo fixo de acordo com o comportamento de cada tipo.
 *
 * Trabalha em duas fases: primeiro todos decidem a direção olhando o mesmo
 * retrato do mundo (CrowdWorldView), depois todos andam. Assim a ordem da
 * lista não muda o resultado, e a mesma seed sempre produz as mesmas posições.
 */
final readonly class EnemySteeringSystem
{
    /**
     * @param  array<string, BehaviorStrategy>  $behaviors  Estratégia por ID de comportamento do arquétipo.
     * @param  float  $cellSize  Tamanho da célula do retrato; o ideal é o raio de separação.
     */
    public function __construct(
        private MovementSystem $movement,
        private array $behaviors,
        private float $cellSize = 40.0,
    ) {
        foreach (array_keys($behaviors) as $id) {
            CombatValidation::requiredId((string) $id, 'behaviorId');
        }
        CombatValidation::positive($cellSize, 'cellSize');
    }

    public function update(GameWorld $world, float $deltaSeconds): void
    {
        CombatValidation::nonNegative($deltaSeconds, 'deltaSeconds');
        // Pausa: o loop da A não avança o tempo, então nada se move.
        if ($deltaSeconds === 0.0) {
            return;
        }

        $crowd = CrowdWorldView::fromViews($world->snapshots(), $world->player()->position(), $this->cellSize);
        $plans = [];
        foreach ($world->enemies() as $enemy) {
            if (! $enemy->isActive() || $enemy->health()->isDead()) {
                continue;
            }
            $self = $crowd->find($enemy->id())
                ?? throw new InvalidArgumentException("Inimigo {$enemy->id()} ausente do retrato.");
            $plans[] = [$enemy, $this->behaviorFor($enemy)->direction($self, $crowd, $deltaSeconds)];
        }

        foreach ($plans as [$enemy, $direction]) {
            $facing = $direction->length() > 0.0 ? $direction->normalized() : null;
            $this->movement->update($enemy, $enemy->facing(), new InputState($direction, $facing),
                $enemy->speed(), $deltaSeconds, true);
        }
    }

    private function behaviorFor(Enemy $enemy): BehaviorStrategy
    {
        $id = $enemy->archetype()->behaviorId;

        return $this->behaviors[$id]
            ?? throw new InvalidArgumentException("Nenhuma estratégia registrada para o comportamento {$id}.");
    }
}
