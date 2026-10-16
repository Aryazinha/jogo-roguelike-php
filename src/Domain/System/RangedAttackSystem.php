<?php

declare(strict_types=1);

namespace Jogo\Domain\System;

use Jogo\Domain\Enemy\EnemyProjectile;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Cooldown;
use Jogo\Domain\World\GameWorld;

/**
 * Faz inimigos à distância atirarem (RF36): cada um tem o seu Cooldown e só
 * dispara quando o jogador está dentro da distância máxima de tiro.
 *
 * A flecha sai na direção em que o jogador estava no momento do disparo.
 * Quem move a flecha e aplica o dano é o EnemyProjectileSystem.
 */
final class RangedAttackSystem
{
    /** @var array<string, Cooldown> */
    private array $cooldowns = [];

    private int $fired = 0;

    /** Devolve quantas flechas saíram neste passo. */
    public function update(GameWorld $world, float $deltaSeconds): int
    {
        CombatValidation::nonNegative($deltaSeconds, 'deltaSeconds');
        $player = $world->player();
        if (! $player->isActive() || $player->health()->isDead()) {
            return 0;
        }

        $shots = 0;
        foreach ($world->enemies() as $enemy) {
            $ranged = $enemy->archetype()->ranged;
            if ($ranged === null || ! $enemy->isActive() || $enemy->health()->isDead()) {
                continue;
            }
            $cooldown = $this->cooldowns['id:'.$enemy->id()] ??= new Cooldown($ranged->shotCooldownSeconds);
            $cooldown->advance($deltaSeconds);

            $toPlayer = $player->position()->subtract($enemy->position());
            $distance = $toPlayer->length();
            if ($distance === 0.0 || $distance > $ranged->maxDistance() || ! $cooldown->tryStart()) {
                continue;
            }

            // Nasce na borda do Skeleton, para não começar já dentro dele.
            $direction = $toPlayer->scale(1.0 / $distance);
            $origin = $enemy->position()->add($direction->scale($enemy->collisionRadius() + $ranged->projectileRadius));
            $this->fired++;
            $world->addProjectile(new EnemyProjectile("arrow-{$this->fired}", $origin, $ranged->projectileRadius,
                $enemy->id(), $enemy->contactDamage(), $direction->scale($ranged->projectileSpeed), $ranged->projectileRange()));
            $shots++;
        }

        return $shots;
    }

    /** Esquece o cooldown de um inimigo que saiu do mundo. */
    public function forget(string $enemyId): void
    {
        unset($this->cooldowns['id:'.$enemyId]);
    }
}
