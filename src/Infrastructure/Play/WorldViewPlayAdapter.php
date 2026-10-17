<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Play;

use InvalidArgumentException;
use Jogo\Domain\Ai\WorldView;
use Jogo\Domain\Value\Vector2;

final readonly class WorldViewPlayAdapter
{
    public function __construct(
        private WorldView $world,
        private float $radius,
    ) {
        if (! is_finite($radius) || $radius <= 0.0) {
            throw new InvalidArgumentException('Raio da consulta visual invalido.');
        }
    }

    /**
     * Usa snapshots readonly da Frente A.
     * Metadados especiais devem ser adicionados pela integracao C/B.
     */
    public function snapshot(?Vector2 $previousPlayer = null): PlayWorldSnapshot
    {
        $player = $this->world->playerPosition();
        $entities = [];

        foreach ($this->world->neighbors($player, $this->radius) as $view) {
            if ($view->isAlive === false || $view->kind === 'player') {
                continue;
            }

            $kind = match ($view->kind) {
                'enemy' => 'enemy',
                'projectile' => 'projectile',
                'chest' => 'chest',
                'pickup' => 'pickup',
                'breakable' => 'breakable',
                default => null,
            };

            if ($kind === null) {
                continue;
            }

            $entities[] = new PlayEntityView(
                $kind,
                $view->position,
                $view->position,
                max(3.0, $view->collisionRadius)
            );
        }

        return new PlayWorldSnapshot(
            $previousPlayer ?? $player,
            $player,
            $entities,
        );
    }
}
