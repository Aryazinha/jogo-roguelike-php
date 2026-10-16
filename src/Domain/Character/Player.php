<?php

declare(strict_types=1);

namespace Jogo\Domain\Character;

use InvalidArgumentException;
use Jogo\Domain\Entity\Damageable;
use Jogo\Domain\Entity\Entity;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\StatModifier;
use Jogo\Domain\Value\StatResolver;
use Jogo\Domain\Value\Stats;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\CombatInventory;

/** Jogador real: compõe vida, inventário e atributos base da Frente A com o personagem escolhido. */
final class Player extends Entity implements Damageable
{
    private readonly Health $health;

    private readonly Stats $baseStats;

    /** @var list<StatModifier> */
    private array $modifiers = [];

    public function __construct(
        string $id,
        Vector2 $position,
        float $collisionRadius,
        private readonly CharacterDefinition $character,
        private readonly CombatInventory $inventory,
    ) {
        parent::__construct($id, $position, $collisionRadius);
        if ($inventory->find($character->startingWeaponId) === null) {
            throw new InvalidArgumentException("O inventário precisa conter a arma inicial {$character->startingWeaponId}.");
        }
        $this->baseStats = new Stats(['vidaMaxima' => $character->maxHealth]);
        $this->health = new Health($character->maxHealth);
    }

    public function character(): CharacterDefinition
    {
        return $this->character;
    }

    public function health(): Health
    {
        return $this->health;
    }

    public function inventory(): CombatInventory
    {
        return $this->inventory;
    }

    public function baseStats(): Stats
    {
        return $this->baseStats;
    }

    /** @return list<StatModifier> */
    public function modifiers(): array
    {
        return $this->modifiers;
    }

    public function stats(): Stats
    {
        return (new StatResolver)->resolve($this->baseStats, $this->modifiers);
    }
}
