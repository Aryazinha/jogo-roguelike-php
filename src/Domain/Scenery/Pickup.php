<?php

declare(strict_types=1);

namespace Jogo\Domain\Scenery;

use InvalidArgumentException;
use Jogo\Domain\Chest\HealingItem;
use Jogo\Domain\Entity\Entity;
use Jogo\Domain\Value\Vector2;

/**
 * Item caído no chão, que o jogador pega ao encostar. Não bloqueia ninguém.
 *
 * Leva junto o que a Frente B precisa para aplicar o efeito: a cura do Steak
 * ou o bônus sorteado. XP pequeno e item especial vão só pelo ID, porque o
 * valor deles é decisão da B.
 */
final class Pickup extends Entity
{
    public const RADIUS = 10.0;

    public function __construct(
        string $id,
        Vector2 $position,
        private readonly string $itemId,
        private readonly ?HealingItem $healing = null,
        private readonly ?TemporaryBonus $bonus = null,
    ) {
        parent::__construct($id, $position, self::RADIUS);
        if ($itemId === DropChance::NOTHING || ! in_array($itemId, DropChance::ITEMS, true)) {
            throw new InvalidArgumentException("item de chão inválido: {$itemId}.");
        }
        if (($itemId === DropChance::STEAK) !== ($healing !== null)) {
            throw new InvalidArgumentException('só o Steak leva cura, e ele sempre leva.');
        }
        if (($itemId === DropChance::TEMPORARY_BONUS) !== ($bonus !== null)) {
            throw new InvalidArgumentException('só o bônus temporário leva bônus, e ele sempre leva.');
        }
    }

    public function itemId(): string
    {
        return $this->itemId;
    }

    public function healing(): ?HealingItem
    {
        return $this->healing;
    }

    public function bonus(): ?TemporaryBonus
    {
        return $this->bonus;
    }
}
