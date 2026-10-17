<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Play;

use Jogo\Domain\Value\Vector2;

final readonly class PlayWorldSnapshot
{
    /**
     * @param  list<PlayEntityView>  $entities
     */
    public function __construct(
        public Vector2 $previousPlayer,
        public Vector2 $currentPlayer,
        public array $entities = [],
        public float $playerRadius = 18.0,
    ) {
        if (! is_finite($playerRadius) || $playerRadius <= 0.0) {
            throw new \InvalidArgumentException('Raio visual do jogador invalido.');
        }
    }
}
