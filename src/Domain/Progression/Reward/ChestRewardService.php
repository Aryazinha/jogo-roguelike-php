<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression\Reward;

use InvalidArgumentException;
use Jogo\Domain\Progression\EvolutionService;
use Jogo\Domain\Progression\WeaponLevels;
use Jogo\Domain\Random\RandomIndex;
use Jogo\Domain\Weapon\CombatInventory;
use Jogo\Domain\Weapon\Upgrade\UpgradeCatalog;
use Jogo\Domain\Weapon\Upgrade\WeaponProgressionService;

/**
 * Recompensa "N upgrades de arma" dos baús (RF19, RF26). Cada upgrade dá prioridade a uma
 * evolução elegível; senão sorteia, com seed, uma melhoria possível entre as armas equipadas.
 * Cada baú é recompensado uma única vez.
 */
final class ChestRewardService
{
    /** @var array<string, true> */
    private array $opened = [];

    public function __construct(
        private readonly UpgradeCatalog $upgrades,
        private readonly WeaponLevels $weaponLevels,
        private readonly WeaponProgressionService $progression,
        private readonly EvolutionService $evolutions,
        private readonly CombatInventory $inventory,
        private readonly RandomIndex $random,
    ) {}

    /** @return list<string> Recompensas aplicadas, como "stone_sword:3" ou "evolucao:mega_tnt". */
    public function grantWeaponUpgrades(string $chestId, int $quantity): array
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('A quantidade de upgrades do baú deve ser >= 1.');
        }
        if (isset($this->opened['id:'.$chestId])) {
            return [];
        }
        $this->opened['id:'.$chestId] = true;
        $applied = [];
        for ($index = 0; $index < $quantity; $index++) {
            $reward = $this->grantOne();
            if ($reward === null) {
                break;
            }
            $applied[] = $reward;
        }

        return $applied;
    }

    private function grantOne(): ?string
    {
        $evolution = $this->evolutions->eligible()[0] ?? null;
        if ($evolution !== null) {
            $this->evolutions->evolve($evolution->definition->id);

            return 'evolucao:'.$evolution->definition->id;
        }
        $available = [];
        foreach ($this->upgrades->all() as $upgrade) {
            $available[$upgrade->weaponId.':'.$upgrade->level] = $upgrade;
        }
        $candidates = [];
        foreach ($this->weaponLevels->baseIds() as $baseId) {
            $key = $baseId.':'.($this->weaponLevels->level($baseId) + 1);
            if (! $this->weaponLevels->isEvolved($baseId) && isset($available[$key])) {
                $candidates[] = $available[$key];
            }
        }
        if ($candidates === []) {
            return null;
        }
        $upgrade = $candidates[$this->random->nextIndex(count($candidates))];
        foreach ($this->inventory->weapons() as $slot => $weapon) {
            if ($weapon->definition()->id === $upgrade->weaponId) {
                $this->progression->applyUpgrade($slot, $upgrade);
                $this->weaponLevels->promote($upgrade->weaponId, $upgrade->level);

                return $upgrade->weaponId.':'.$upgrade->level;
            }
        }

        return null;
    }
}
