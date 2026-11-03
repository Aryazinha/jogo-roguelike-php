<?php

declare(strict_types=1);

namespace Jogo\Domain\System;

use Jogo\Domain\Entity\Entity;
use Jogo\Domain\Scenery\Breakable;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;

/**
 * Impede jogador e inimigos de atravessar Crate e Ore (RF38).
 *
 * Roda depois do movimento: quem terminou o passo dentro de um elemento que
 * bloqueia é empurrado para a borda dele, na direção de onde estava. Grass e
 * itens no chão não bloqueiam. Os quebráveis não andam, então a grade é
 * montada a cada chamada só com os que ainda estão inteiros.
 */
final class SceneryBlocker
{
    private const CELL = 64.0;

    /** @var array<int, list<Breakable>> */
    private array $cells = [];

    public function update(GameWorld $world): void
    {
        $this->cells = [];
        foreach ($world->breakables() as $breakable) {
            if ($breakable->blocksMovement() && $breakable->isActive() && ! $breakable->health()->isDead()) {
                $this->cells[$this->key($breakable->position()->x(), $breakable->position()->y())][] = $breakable;
            }
        }
        if ($this->cells === []) {
            return;
        }

        $bounds = $world->layout()->bounds();
        foreach ([$world->player(), ...$world->enemies()] as $mover) {
            if ($mover->isActive()) {
                $this->pushOut($mover);
                $mover->moveTo($bounds->clamp($mover->position()));
            }
        }
    }

    private function pushOut(Entity $mover): void
    {
        $x = $mover->position()->x();
        $y = $mover->position()->y();
        $cx = (int) floor($x / self::CELL);
        $cy = (int) floor($y / self::CELL);
        for ($i = $cx - 1; $i <= $cx + 1; $i++) {
            for ($j = $cy - 1; $j <= $cy + 1; $j++) {
                foreach ($this->cells[$i * 1_000_003 + $j] ?? [] as $block) {
                    $minimum = $mover->collisionRadius() + $block->collisionRadius();
                    $away = $mover->position()->subtract($block->position());
                    $distance = $away->length();
                    if ($distance >= $minimum) {
                        continue;
                    }
                    // Exatamente no centro não há direção: sai para a direita.
                    $direction = $distance > 0.0 ? $away->scale(1.0 / $distance) : new Vector2(1.0, 0.0);
                    $mover->moveTo($block->position()->add($direction->scale($minimum)));
                }
            }
        }
    }

    private function key(float $x, float $y): int
    {
        return (int) floor($x / self::CELL) * 1_000_003 + (int) floor($y / self::CELL);
    }
}
