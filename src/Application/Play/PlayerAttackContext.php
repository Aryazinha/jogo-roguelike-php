<?php

declare(strict_types=1);

namespace Jogo\Application\Play;

use Jogo\Domain\Entity\Entity;
use Jogo\Domain\Value\Facing;
use Jogo\Domain\Weapon\AttackContextProvider;
use Jogo\Domain\Weapon\Targeting\TargetingContext;

/** De onde e para onde o jogador ataca: a posição atual e a direção em que olha. */
final readonly class PlayerAttackContext implements AttackContextProvider
{
    public function __construct(private Entity $player, private Facing $facing) {}

    public function context(float $range): TargetingContext
    {
        return new TargetingContext($this->player->position(), $this->facing->direction(), $range);
    }
}
