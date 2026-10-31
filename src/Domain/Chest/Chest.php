<?php

declare(strict_types=1);

namespace Jogo\Domain\Chest;

use Jogo\Domain\Entity\Entity;
use Jogo\Domain\Value\Vector2;

/** Baú no mapa (RF26). A raridade é sorteada quando ele nasce; abre uma única vez. */
final class Chest extends Entity
{
    public const RADIUS = 16.0;

    private ?ChestReward $reward = null;

    public function __construct(string $id, Vector2 $position, private readonly ChestRarity $rarity)
    {
        parent::__construct($id, $position, self::RADIUS);
    }

    public function rarity(): ChestRarity
    {
        return $this->rarity;
    }

    public function isOpened(): bool
    {
        return $this->reward !== null;
    }

    /** Devolve a recompensa na primeira vez e null nas seguintes. */
    public function open(float $elapsedSeconds): ?ChestReward
    {
        if ($this->reward !== null) {
            return null;
        }
        $this->reward = new ChestReward($this->id(), $this->rarity->id, $this->rarity->rewardType,
            $this->rarity->rewardQuantity, $elapsedSeconds);
        $this->deactivate();

        return $this->reward;
    }
}
