<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression\LevelUp;

use Closure;
use InvalidArgumentException;
use Jogo\Domain\Event\EventBusInterface;
use Jogo\Domain\Event\LevelUp;
use Jogo\Domain\Progression\EvolutionService;
use Jogo\Domain\Progression\PassiveInventory;
use Jogo\Domain\Progression\ProgressionEffects;
use Jogo\Domain\Progression\WeaponLevels;
use Jogo\Domain\Weapon\CombatInventory;
use Jogo\Domain\Weapon\Upgrade\UpgradeCatalog;
use Jogo\Domain\Weapon\Upgrade\WeaponProgressionService;
use Jogo\Domain\Weapon\WeaponCatalog;
use Jogo\Domain\Weapon\WeaponDefinition;
use LogicException;
use OutOfBoundsException;

/**
 * Fila de escolhas do RF14: cada LevelUp gera uma escolha pendente. Enquanto houver escolha
 * pendente, Application mantém a partida pausada e a Frente D apresenta `currentOffer()`.
 * A escolha confirmada é aplicada uma única vez.
 */
final class LevelUpService
{
    private int $pendingLevels = 0;

    /** @var list<LevelUpOption>|null */
    private ?array $offer = null;

    /** @var Closure(WeaponDefinition): bool */
    private readonly Closure $execute;

    /** @param callable(WeaponDefinition): bool $execute Executor de ataques da Frente A para armas novas. */
    public function __construct(
        private readonly string $playerId,
        private readonly LevelUpOfferGenerator $generator,
        private readonly WeaponCatalog $weapons,
        private readonly UpgradeCatalog $upgrades,
        private readonly WeaponProgressionService $progression,
        private readonly PassiveInventory $passives,
        private readonly ProgressionEffects $effects,
        private readonly WeaponLevels $weaponLevels,
        private readonly CombatInventory $inventory,
        callable $execute,
        private readonly ?EvolutionService $evolutions = null,
    ) {
        $this->execute = Closure::fromCallable($execute);
        foreach ($inventory->weapons() as $weapon) {
            $id = $weapon->baseDefinition()->id;
            if ($weaponLevels->baseIdOf($id) === null) {
                $weaponLevels->register($id);
            }
        }
    }

    public function subscribe(EventBusInterface $events): void
    {
        $events->subscribe(LevelUp::class, function (LevelUp $event): void {
            if ($event->playerId === $this->playerId) {
                $this->pendingLevels++;
            }
        });
    }

    public function hasPendingChoice(): bool
    {
        return $this->pendingLevels > 0;
    }

    public function pendingLevels(): int
    {
        return $this->pendingLevels;
    }

    /**
     * Opções da escolha pendente. Sem opção elegível, o nível é consumido e a lista vem vazia.
     *
     * @return list<LevelUpOption>
     */
    public function currentOffer(): array
    {
        if ($this->pendingLevels === 0) {
            throw new LogicException('Não há escolha de level up pendente.');
        }
        $this->offer ??= $this->generator->generate();
        if ($this->offer === []) {
            $this->offer = null;
            $this->pendingLevels--;

            return [];
        }

        return $this->offer;
    }

    public function choose(int $index, ?int $replaceSlot = null): LevelUpOption
    {
        $offer = $this->offer ?? throw new LogicException('Nenhuma oferta foi apresentada.');
        $option = $offer[$index] ?? throw new OutOfBoundsException("Opção {$index} inexistente.");
        match ($option->kind) {
            LevelUpOptionKind::NEW_WEAPON => $this->addWeapon($option, $replaceSlot),
            LevelUpOptionKind::UPGRADE_WEAPON => $this->upgradeWeapon($option, $replaceSlot),
            LevelUpOptionKind::PASSIVE => $this->acquirePassive($option, $replaceSlot),
        };
        $this->offer = null;
        $this->pendingLevels--;

        return $option;
    }

    private function addWeapon(LevelUpOption $option, ?int $replaceSlot): void
    {
        $definition = $this->weapons->get($option->contentId);
        if (! $option->requiresReplacement) {
            $this->assertNoReplacement($replaceSlot);
            $slot = $this->inventory->add($definition, $this->executor());
            $this->weaponLevels->register($definition->id);
            $this->effects->syncWeapon($slot);

            return;
        }
        if ($replaceSlot === null) {
            throw new InvalidArgumentException('Inventário cheio: informe o slot da arma a substituir.');
        }
        $current = $this->inventory->at($replaceSlot) ?? throw new OutOfBoundsException("O slot {$replaceSlot} está vazio.");
        $previousBase = $this->weaponLevels->baseIdOf($current->definition()->id)
            ?? throw new LogicException("Arma sem nível registrado: {$current->definition()->id}.");
        $this->inventory->replace($replaceSlot, $definition, $this->executor());
        $this->weaponLevels->remove($previousBase);
        $this->weaponLevels->register($definition->id);
        $this->effects->syncWeapon($replaceSlot);
    }

    private function upgradeWeapon(LevelUpOption $option, ?int $replaceSlot): void
    {
        $this->assertNoReplacement($replaceSlot);
        if ($option->evolutionId !== null) {
            $evolutions = $this->evolutions ?? throw new LogicException('Evoluções não estão habilitadas nesta partida.');
            $evolutions->evolve($option->evolutionId);

            return;
        }
        $upgrade = $this->upgrades->get($option->contentId, $option->level);
        $this->progression->applyUpgrade($this->slotOf($option->contentId), $upgrade);
        $this->weaponLevels->promote($option->contentId, $option->level);
    }

    private function acquirePassive(LevelUpOption $option, ?int $replaceSlot): void
    {
        $this->assertNoReplacement($replaceSlot);
        if ($this->passives->level($option->contentId) + 1 !== $option->level) {
            throw new LogicException("A oferta de {$option->contentId} está desatualizada.");
        }
        $this->passives->acquire($option->contentId);
    }

    private function slotOf(string $weaponId): int
    {
        foreach ($this->inventory->weapons() as $slot => $weapon) {
            if ($weapon->definition()->id === $weaponId) {
                return $slot;
            }
        }
        throw new InvalidArgumentException("A arma {$weaponId} não está equipada.");
    }

    private function assertNoReplacement(?int $replaceSlot): void
    {
        if ($replaceSlot !== null) {
            throw new InvalidArgumentException('Esta opção não substitui arma.');
        }
    }

    /** @return Closure(WeaponDefinition): bool */
    private function executor(): Closure
    {
        return $this->execute;
    }
}
