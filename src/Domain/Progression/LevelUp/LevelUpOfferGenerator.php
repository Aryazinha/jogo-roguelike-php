<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression\LevelUp;

use Jogo\Domain\Progression\PassiveCatalog;
use Jogo\Domain\Progression\PassiveInventory;
use Jogo\Domain\Progression\WeaponLevels;
use Jogo\Domain\Random\RandomIndex;
use Jogo\Domain\Weapon\CombatInventory;
use Jogo\Domain\Weapon\Upgrade\UpgradeCatalog;
use Jogo\Domain\Weapon\WeaponCatalog;

/**
 * Sorteia até três opções elegíveis, tentando primeiro uma de cada tipo do RF14 e completando
 * com as restantes. Com a mesma seed e o mesmo estado, gera sempre as mesmas opções.
 */
final readonly class LevelUpOfferGenerator
{
    public const OPTION_COUNT = 3;

    public function __construct(
        private WeaponCatalog $weapons,
        private UpgradeCatalog $upgrades,
        private PassiveCatalog $passiveCatalog,
        private PassiveInventory $passives,
        private WeaponLevels $weaponLevels,
        private CombatInventory $inventory,
        private RandomIndex $random,
    ) {}

    /** @return list<LevelUpOption> */
    public function generate(): array
    {
        $pools = [
            $this->newWeaponOptions(),
            $this->upgradeOptions(),
            $this->passiveOptions(),
        ];
        $chosen = [];
        foreach ($pools as $index => $pool) {
            if ($pool !== []) {
                $chosen[] = $this->take($pools[$index]);
            }
        }
        $rest = array_merge(...$pools);
        while (count($chosen) < self::OPTION_COUNT && $rest !== []) {
            $chosen[] = $this->take($rest);
        }

        return $chosen;
    }

    /** @return list<LevelUpOption> */
    private function newWeaponOptions(): array
    {
        $full = $this->inventory->count() >= $this->inventory->capacity();
        $options = [];
        foreach ($this->weapons->all() as $definition) {
            if (! $this->weaponLevels->has($definition->id)) {
                $options[] = new LevelUpOption(LevelUpOptionKind::NEW_WEAPON, $definition->id,
                    WeaponLevels::STARTING_LEVEL, $full);
            }
        }

        return $options;
    }

    /** @return list<LevelUpOption> */
    private function upgradeOptions(): array
    {
        $available = [];
        foreach ($this->upgrades->all() as $upgrade) {
            $available[$upgrade->weaponId.':'.$upgrade->level] = true;
        }
        $options = [];
        foreach ($this->weaponLevels->baseIds() as $baseId) {
            if ($this->weaponLevels->isEvolved($baseId)) {
                continue;
            }
            $next = $this->weaponLevels->level($baseId) + 1;
            if (isset($available[$baseId.':'.$next])) {
                $options[] = new LevelUpOption(LevelUpOptionKind::UPGRADE_WEAPON, $baseId, $next);
            }
        }

        return $options;
    }

    /** @return list<LevelUpOption> */
    private function passiveOptions(): array
    {
        $options = [];
        foreach ($this->passiveCatalog->ids() as $passiveId) {
            if ($this->passives->canAcquire($passiveId)) {
                $options[] = new LevelUpOption(LevelUpOptionKind::PASSIVE, $passiveId, $this->passives->level($passiveId) + 1);
            }
        }

        return $options;
    }

    /**
     * Remove e devolve um item sorteado; a opção sai também das demais listas.
     *
     * @param  list<LevelUpOption>  $pool
     */
    private function take(array &$pool): LevelUpOption
    {
        $index = $this->random->nextIndex(count($pool));
        $option = $pool[$index];
        array_splice($pool, $index, 1);

        return $option;
    }
}
