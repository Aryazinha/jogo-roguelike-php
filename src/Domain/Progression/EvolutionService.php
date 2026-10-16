<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression;

use InvalidArgumentException;
use Jogo\Domain\Weapon\CombatInventory;
use Jogo\Domain\Weapon\Upgrade\EvolutionCatalog;
use Jogo\Domain\Weapon\Upgrade\EvolutionDefinition;
use Jogo\Domain\Weapon\Upgrade\WeaponProgressionService;

/**
 * Evoluções especiais (RF37): a B decide a elegibilidade e a A executa a troca de definição.
 * Uma arma evolui uma única vez; depois da troca, os efeitos de passivos são reaplicados.
 */
final readonly class EvolutionService
{
    public function __construct(
        private EvolutionCatalog $evolutions,
        private WeaponLevels $weaponLevels,
        private PassiveInventory $passives,
        private WeaponProgressionService $progression,
        private ProgressionEffects $effects,
        private CombatInventory $inventory,
    ) {}

    public function eligibleFor(string $baseWeaponId): ?EvolutionDefinition
    {
        foreach ($this->evolutions->all() as $evolution) {
            if ($evolution->weaponId === $baseWeaponId && $this->missingRequirement($evolution) === null) {
                return $evolution;
            }
        }

        return null;
    }

    /** @return list<EvolutionDefinition> */
    public function eligible(): array
    {
        return array_values(array_filter(
            $this->evolutions->all(),
            fn (EvolutionDefinition $evolution): bool => $this->missingRequirement($evolution) === null,
        ));
    }

    /** Evolui a arma e devolve o slot; requisito ausente falha sem alterar nada. */
    public function evolve(string $evolutionId): int
    {
        $evolution = $this->evolutions->get($evolutionId);
        $missing = $this->missingRequirement($evolution);
        if ($missing !== null) {
            throw new InvalidArgumentException("{$evolutionId}: {$missing}");
        }
        $slot = $this->slotOf($evolution->weaponId);
        $this->progression->evolve($slot, $evolutionId);
        $this->weaponLevels->evolve($evolution->weaponId, $evolution->definition->id);
        $this->effects->syncWeapon($slot);

        return $slot;
    }

    private function missingRequirement(EvolutionDefinition $evolution): ?string
    {
        $weaponId = $evolution->weaponId;
        if (! $this->weaponLevels->has($weaponId) || $this->slotOf($weaponId, required: false) === null) {
            return "a arma {$weaponId} não está equipada.";
        }
        if ($this->weaponLevels->isEvolved($weaponId)) {
            return "a arma {$weaponId} já evoluiu.";
        }
        if ($this->weaponLevels->level($weaponId) < $evolution->weaponLevel) {
            return "{$weaponId} precisa estar no nível {$evolution->weaponLevel}.";
        }
        if ($this->passives->level($evolution->passiveId) < $evolution->passiveLevel) {
            return "{$evolution->passiveId} precisa estar no nível {$evolution->passiveLevel}.";
        }

        return null;
    }

    /** @return ($required is true ? int : ?int) */
    private function slotOf(string $weaponId, bool $required = true): ?int
    {
        foreach ($this->inventory->weapons() as $slot => $weapon) {
            if ($weapon->definition()->id === $weaponId) {
                return $slot;
            }
        }
        if ($required) {
            throw new InvalidArgumentException("A arma {$weaponId} não está equipada.");
        }

        return null;
    }
}
