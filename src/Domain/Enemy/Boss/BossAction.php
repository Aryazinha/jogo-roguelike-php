<?php

declare(strict_types=1);

namespace Jogo\Domain\Enemy\Boss;

use Jogo\Domain\Value\Vector2;

/** O que o Warden quer fazer neste passo; quem executa é o BossSystem. */
final readonly class BossAction
{
    /**
     * @param  Vector2  $direction  Direção de comprimento 0 ou 1.
     * @param  float  $speedMultiplier  Multiplica a velocidade do arquétipo (investida, fúria).
     * @param  int  $summon  Quantos inimigos invocar agora (zero quase sempre).
     */
    public function __construct(
        public Vector2 $direction,
        public float $speedMultiplier,
        public int $summon = 0,
    ) {}

    public static function standStill(int $summon = 0): self
    {
        return new self(Vector2::zero(), 0.0, $summon);
    }
}
