<?php

declare(strict_types=1);

namespace Jogo\Domain\World;

use InvalidArgumentException;
use Jogo\Domain\Random\RandomIndex;
use Jogo\Domain\Value\Vector2;

/**
 * Sorteia onde um inimigo surge: num anel em volta do jogador, dentro do mapa.
 *
 * Perto da borda parte do anel cai fora do mapa. Nesse caso sorteia de novo
 * algumas vezes e, se nada servir, usa o canto mais distante do jogador.
 * Esse canto fica a pelo menos spawnMinDistance, porque o MapLayout já
 * recusa mapas pequenos demais para isso.
 */
final readonly class SpawnPointPicker
{
    /** Resolução do ângulo sorteado: 1 grau é suficiente para espalhar os inimigos. */
    private const ANGLE_STEPS = 360;

    public function __construct(
        private MapLayout $layout,
        private RandomIndex $random,
        private int $attempts = 8,
    ) {
        if ($attempts < 1) {
            throw new InvalidArgumentException('attempts deve ser maior ou igual a um.');
        }
    }

    public function pick(Vector2 $player): Vector2
    {
        $min = $this->layout->spawnMinDistance;
        $range = (int) floor($this->layout->spawnMaxDistance - $min);

        for ($attempt = 0; $attempt < $this->attempts; $attempt++) {
            $angle = deg2rad($this->random->nextIndex(self::ANGLE_STEPS));
            $distance = $min + $this->random->nextIndex($range + 1);
            $point = $player->add(new Vector2(cos($angle) * $distance, sin($angle) * $distance));

            if ($this->layout->contains($point)) {
                return $point;
            }
        }

        return $this->farthestCorner($player);
    }

    private function farthestCorner(Vector2 $player): Vector2
    {
        $bounds = $this->layout->bounds();
        $x = abs($player->x() - $bounds->minX) > abs($bounds->maxX - $player->x()) ? $bounds->minX : $bounds->maxX;
        $y = abs($player->y() - $bounds->minY) > abs($bounds->maxY - $player->y()) ? $bounds->minY : $bounds->maxY;

        return new Vector2($x, $y);
    }
}
