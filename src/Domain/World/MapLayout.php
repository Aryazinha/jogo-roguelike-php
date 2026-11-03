<?php

declare(strict_types=1);

namespace Jogo\Domain\World;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\MapBounds;
use Jogo\Domain\Value\Vector2;

/**
 * Dimensões lógicas do mapa e a faixa de distância em que inimigos surgem (RF03).
 *
 * O mapa começa em (0, 0). A margem impede que entidades fiquem coladas na
 * borda, onde metade do sprite ficaria fora da área desenhada.
 */
final readonly class MapLayout
{
    public function __construct(
        public float $width,
        public float $height,
        public float $borderMargin,
        public float $spawnMinDistance,
        public float $spawnMaxDistance,
    ) {
        CombatValidation::positive($width, 'width');
        CombatValidation::positive($height, 'height');
        CombatValidation::nonNegative($borderMargin, 'borderMargin');
        CombatValidation::positive($spawnMinDistance, 'spawnMinDistance');
        CombatValidation::positive($spawnMaxDistance, 'spawnMaxDistance');

        if ($borderMargin * 2.0 >= min($width, $height)) {
            throw new InvalidArgumentException('borderMargin não deixa área útil no mapa.');
        }
        if ($spawnMaxDistance < $spawnMinDistance) {
            throw new InvalidArgumentException('spawnMaxDistance deve ser maior ou igual a spawnMinDistance.');
        }
        // Com o jogador no centro, ainda precisa existir algum ponto válido de spawn.
        $halfDiagonal = hypot($width / 2.0 - $borderMargin, $height / 2.0 - $borderMargin);
        if ($spawnMinDistance > $halfDiagonal) {
            throw new InvalidArgumentException('spawnMinDistance é maior que o mapa permite.');
        }
    }

    /** Área em que jogador e inimigos podem ficar; usada pelo MovementSystem da A. */
    public function bounds(): MapBounds
    {
        return new MapBounds($this->borderMargin, $this->borderMargin,
            $this->width - $this->borderMargin, $this->height - $this->borderMargin);
    }

    public function playerStart(): Vector2
    {
        return new Vector2($this->width / 2.0, $this->height / 2.0);
    }

    public function contains(Vector2 $position): bool
    {
        return $this->bounds()->clamp($position)->equals($position);
    }
}
