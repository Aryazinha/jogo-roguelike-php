<?php

declare(strict_types=1);

namespace Jogo\Domain\System;

use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\World\GameWorld;

/**
 * Move os projéteis dos inimigos e aplica o dano quando acertam o jogador.
 *
 * O acerto é testado no trecho inteiro percorrido no passo, com o teste de
 * segmento da A, para uma flecha rápida não pular por cima do jogador. O
 * dano passa pelo EnemyAttackSystem, então respeita a invulnerabilidade
 * do contato (a flecha acerta e some mesmo se o jogador estiver protegido).
 */
final readonly class EnemyProjectileSystem
{
    public function __construct(private EnemyAttackSystem $attacks) {}

    /** Devolve quantas flechas acertaram o jogador neste passo. */
    public function update(GameWorld $world, float $deltaSeconds, float $elapsedSeconds): int
    {
        CombatValidation::nonNegative($deltaSeconds, 'deltaSeconds');
        $player = $world->player();
        $hits = 0;

        foreach ($world->projectiles() as $projectile) {
            if (! $projectile->isActive()) {
                continue;
            }
            $start = $projectile->advance($deltaSeconds);
            $end = $projectile->position();

            $touches = $player->isActive() && CollisionSystem::segmentCircleFirstHit($start, $end, $player->position(),
                $player->collisionRadius() + $projectile->collisionRadius()) !== null;
            if ($touches) {
                $this->attacks->strike($world, $projectile->id(), $projectile->damage(), $elapsedSeconds);
                $projectile->deactivate();
                $hits++;
            } elseif (! $world->layout()->contains($end)) {
                $projectile->deactivate();
            }
        }

        return $hits;
    }
}
