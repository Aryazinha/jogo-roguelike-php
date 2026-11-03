<?php

declare(strict_types=1);

namespace Jogo\Domain\Enemy;

use Jogo\Domain\Entity\Entity;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Vector2;

/**
 * Projétil disparado por um inimigo, como a flecha do Skeleton (RF36).
 *
 * Voa em linha reta, na direção em que foi disparado, até acertar o jogador
 * ou percorrer o alcance. Não persegue: dá para desviar andando de lado.
 */
final class EnemyProjectile extends Entity
{
    private float $remainingDistance;

    public function __construct(
        string $id,
        Vector2 $position,
        float $radius,
        private readonly string $ownerId,
        private readonly float $damage,
        private readonly Vector2 $velocity,
        float $range,
    ) {
        parent::__construct($id, $position, $radius);
        CombatValidation::requiredId($ownerId, 'ownerId');
        CombatValidation::nonNegative($damage, 'damage');
        CombatValidation::positive($range, 'range');
        $this->remainingDistance = $range;
    }

    public function ownerId(): string
    {
        return $this->ownerId;
    }

    public function damage(): float
    {
        return $this->damage;
    }

    public function velocity(): Vector2
    {
        return $this->velocity;
    }

    /**
     * Anda um passo e devolve de onde saiu, para o teste de colisão cobrir o
     * trecho inteiro e uma flecha rápida não atravessar o jogador entre passos.
     */
    public function advance(float $deltaSeconds): Vector2
    {
        CombatValidation::nonNegative($deltaSeconds, 'deltaSeconds');
        $start = $this->position();
        if (! $this->isActive()) {
            return $start;
        }
        $this->translate($this->velocity->scale($deltaSeconds));
        $this->remainingDistance -= $this->velocity->length() * $deltaSeconds;
        if ($this->remainingDistance <= 0.0) {
            $this->deactivate();
        }

        return $start;
    }
}
