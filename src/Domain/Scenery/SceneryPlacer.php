<?php

declare(strict_types=1);

namespace Jogo\Domain\Scenery;

use Jogo\Domain\Random\RandomIndex;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;

/**
 * Espalha os quebráveis pelo mapa no início da partida (RF03, RF38).
 *
 * A quantidade vem da densidade de scenery.json. Cada elemento nasce dentro
 * do mapa, sem encostar em outro e longe do ponto inicial do jogador, para
 * ele não começar preso entre caixotes.
 */
final readonly class SceneryPlacer
{
    /** Tentativas por elemento antes de desistir dele (mapa muito cheio). */
    private const ATTEMPTS = 20;

    /** Folga mínima entre dois elementos, além da soma dos raios. */
    private const GAP = 4.0;

    public function __construct(
        private ScenerySettings $settings,
        private RandomIndex $random,
        private float $playerSafeRadius = 150.0,
    ) {
        CombatValidation::nonNegative($playerSafeRadius, 'playerSafeRadius');
    }

    /** @return int Quantos elementos foram colocados. */
    public function place(GameWorld $world): int
    {
        $bounds = $world->layout()->bounds();
        $count = $this->settings->countFor(($bounds->maxX - $bounds->minX) * ($bounds->maxY - $bounds->minY));
        $types = $this->settings->types();
        $player = $world->player()->position();
        $placed = [];

        for ($i = 0; $i < $count; $i++) {
            $type = $types[$this->random->nextIndex(count($types))];
            $radius = $type->collisionRadius;
            for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
                $position = new Vector2(
                    $bounds->minX + $radius + $this->random->nextIndex(max(1, (int) ($bounds->maxX - $bounds->minX - 2 * $radius))),
                    $bounds->minY + $radius + $this->random->nextIndex(max(1, (int) ($bounds->maxY - $bounds->minY - 2 * $radius))),
                );
                if ($this->fits($position, $radius, $player, $placed)) {
                    $breakable = new Breakable($type->id.'-'.(count($placed) + 1), $type, $position);
                    $world->addBreakable($breakable);
                    $placed[] = $breakable;
                    break;
                }
            }
        }

        return count($placed);
    }

    /** @param list<Breakable> $placed */
    private function fits(Vector2 $position, float $radius, Vector2 $player, array $placed): bool
    {
        if ($position->distanceTo($player) < $this->playerSafeRadius + $radius) {
            return false;
        }
        foreach ($placed as $other) {
            if ($position->distanceTo($other->position()) < $radius + $other->collisionRadius() + self::GAP) {
                return false;
            }
        }

        return true;
    }
}
