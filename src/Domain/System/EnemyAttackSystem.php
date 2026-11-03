<?php

declare(strict_types=1);

namespace Jogo\Domain\System;

use Jogo\Domain\Enemy\Enemy;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Damage;
use Jogo\Domain\World\GameWorld;

/**
 * Lado inimigo → jogador do RF08.
 *
 * Decide quando e quanto o jogador apanha; quem tira a vida e publica
 * PlayerDamaged/PlayerDied continua sendo o DamageSystem da Frente A.
 *
 * Regras:
 * - contato é encostar: os círculos se tocam, tangência inclusive;
 * - depois de um golpe o jogador fica invulnerável por um tempo, contado
 *   em tempo de simulação, então a pausa não consome a invulnerabilidade;
 * - no máximo um golpe por passo; se vários encostam, vale o de maior dano
 *   e, empatando, o de menor ID, para o resultado não depender da ordem;
 * - ataques à distância (flecha do Skeleton, investida do boss) entram por
 *   strike() e seguem a mesma invulnerabilidade.
 */
final class EnemyAttackSystem
{
    private float $invulnerableUntil = -INF;

    public function __construct(
        private readonly DamageSystem $damage,
        private readonly float $invulnerabilitySeconds,
        private readonly CollisionSystem $collision = new CollisionSystem,
    ) {
        CombatValidation::nonNegative($invulnerabilitySeconds, 'invulnerabilitySeconds');
    }

    /** Aplica o dano de contato do passo. Devolve o dano aplicado (zero se ninguém acertou). */
    public function update(GameWorld $world, float $elapsedSeconds): float
    {
        CombatValidation::nonNegative($elapsedSeconds, 'elapsedSeconds');
        if ($this->isInvulnerable($elapsedSeconds)) {
            return 0.0;
        }

        $attacker = $this->strongestTouching($world);
        if ($attacker === null) {
            return 0.0;
        }

        return $this->strike($world, $attacker->id(), $attacker->contactDamage(), $elapsedSeconds);
    }

    /** Golpe vindo de um ataque específico; respeita a mesma invulnerabilidade do contato. */
    public function strike(GameWorld $world, string $sourceId, float $amount, float $elapsedSeconds): float
    {
        CombatValidation::requiredId($sourceId, 'sourceId');
        CombatValidation::nonNegative($amount, 'amount');
        CombatValidation::nonNegative($elapsedSeconds, 'elapsedSeconds');
        if ($amount === 0.0 || $this->isInvulnerable($elapsedSeconds)) {
            return 0.0;
        }

        $applied = $this->damage->damagePlayer($world->player(), new Damage($amount, $sourceId), $elapsedSeconds);
        if ($applied > 0.0) {
            $this->invulnerableUntil = $elapsedSeconds + $this->invulnerabilitySeconds;
        }

        return $applied;
    }

    public function isInvulnerable(float $elapsedSeconds): bool
    {
        return $elapsedSeconds < $this->invulnerableUntil;
    }

    private function strongestTouching(GameWorld $world): ?Enemy
    {
        $player = $world->player();
        if (! $player->isActive() || $player->health()->isDead()) {
            return null;
        }

        $best = null;
        foreach ($world->enemies() as $enemy) {
            if ($enemy->health()->isDead() || $enemy->contactDamage() === 0.0 || ! $this->collision->intersects($enemy, $player)) {
                continue;
            }
            if ($best === null
                || $enemy->contactDamage() > $best->contactDamage()
                || ($enemy->contactDamage() === $best->contactDamage() && strcmp($enemy->id(), $best->id()) < 0)) {
                $best = $enemy;
            }
        }

        return $best;
    }
}
