<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon\Upgrade;

use InvalidArgumentException;
use Jogo\Domain\Value\StatModifier;
use Jogo\Domain\Weapon\CombatInventory;
use Jogo\Domain\Weapon\Weapon;
use Jogo\Domain\Weapon\WeaponMutationGuard;

/** Executa somente comandos já concedidos pela B, em ponto seguro entre atualizações. */
final readonly class WeaponProgressionService
{
    public function __construct(private CombatInventory $inventory, private EvolutionCatalog $evolutions,
        private WeaponMutationGuard $combat) {}

    /** @param list<StatModifier> $modifiers */
    public function apply(int $slot, array $modifiers): void
    {
        $this->weapon($slot)->applyModifiers($modifiers);
    }

    public function applyUpgrade(int $slot, UpgradeDefinition $upgrade): void
    {
        $weapon = $this->weapon($slot);
        if ($weapon->baseDefinition()->id !== $upgrade->weaponId) {
            throw new InvalidArgumentException('O upgrade não pertence à arma equipada.');
        }
        $weapon->applyModifiers($upgrade->modifiers);
    }

    public function remove(int $slot, string $source): bool
    {
        return $this->weapon($slot)->removeModifier($source);
    }

    public function evolve(int $slot, string $evolutionId): void
    {
        $weapon = $this->weapon($slot);
        $evolution = $this->evolutions->get($evolutionId);
        if ($weapon->definition()->id !== $evolution->weaponId) {
            throw new InvalidArgumentException('A evolução não substitui a arma equipada.');
        }
        $this->inventory->evolve($slot, $evolution->definition);
    }

    private function weapon(int $slot): Weapon
    {
        $this->combat->assertSafe();
        $this->inventory->assertProgressionSafe();

        return $this->inventory->at($slot) ?? throw new InvalidArgumentException("O slot {$slot} não contém uma arma equipada.");
    }
}
