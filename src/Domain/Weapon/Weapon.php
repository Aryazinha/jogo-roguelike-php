<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon;

use Closure;
use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Cooldown;
use Jogo\Domain\Value\StatModifier;
use LogicException;

/** Execução genérica: cada instância possui seu próprio cooldown. */
final class Weapon
{
    private readonly Cooldown $cooldown;

    /** @var Closure(WeaponDefinition): bool */
    private readonly Closure $execute;

    private readonly ?WeaponMutationGuard $mutationGuard;

    private bool $updating = false;

    private WeaponDefinition $baseDefinition;

    /** @var list<StatModifier> */
    private array $modifiers = [];

    /** @param callable(WeaponDefinition): bool $execute Retorna se a tentativa foi aceita. */
    public function __construct(private WeaponDefinition $definition, callable $execute)
    {
        $this->baseDefinition = $definition;
        $this->mutationGuard = $execute instanceof WeaponMutationGuard ? $execute : null;
        $this->cooldown = new Cooldown($definition->cooldownSeconds);
        $this->execute = Closure::fromCallable($execute);
    }

    public function definition(): WeaponDefinition
    {
        return $this->definition;
    }

    public function baseDefinition(): WeaponDefinition
    {
        return $this->baseDefinition;
    }

    /** @return list<StatModifier> */
    public function modifiers(): array
    {
        return $this->modifiers;
    }

    public function modifier(string $source): ?StatModifier
    {
        CombatValidation::requiredId($source, 'source');
        foreach ($this->modifiers as $modifier) {
            if ($modifier->source === $source) {
                return $modifier;
            }
        }

        return null;
    }

    /** @param list<StatModifier> $modifiers */
    public function applyModifiers(array $modifiers): void
    {
        $this->assertModifiable();
        $next = array_merge($this->modifiers, $modifiers);
        $resolved = new ResolvedWeaponStats($this->baseDefinition, $next);
        usort($next, static fn (StatModifier $a, StatModifier $b): int => strcmp($a->source, $b->source));
        $this->cooldown->changeDuration($resolved->definition->cooldownSeconds);
        $this->definition = $resolved->definition;
        $this->modifiers = $next;
    }

    public function removeModifier(string $source): bool
    {
        $this->assertModifiable();
        if ($this->modifier($source) === null) {
            return false;
        }
        $next = array_values(array_filter($this->modifiers, static fn (StatModifier $m): bool => $m->source !== $source));
        $resolved = new ResolvedWeaponStats($this->baseDefinition, $next);
        $this->cooldown->changeDuration($resolved->definition->cooldownSeconds);
        $this->definition = $resolved->definition;
        $this->modifiers = $next;

        return true;
    }

    /** Operação estrutural; a autorização e a compatibilidade vêm do serviço/inventário. */
    public function evolve(WeaponDefinition $definition): void
    {
        $this->assertModifiable();
        if ($definition->id === $this->definition->id) {
            throw new InvalidArgumentException('A evolução precisa trocar o ID da arma.');
        }
        $this->cooldown->changeDuration($definition->cooldownSeconds);
        $this->baseDefinition = $definition;
        $this->definition = $definition;
        $this->modifiers = [];
    }

    private function assertModifiable(): void
    {
        if ($this->updating) {
            throw new LogicException('Não é permitido modificar uma arma durante sua atualização.');
        }
        // O executor A7 protege também a fase de dano, após a atualização do inventário.
        $this->mutationGuard?->assertSafe();
    }

    public function isReady(): bool
    {
        return $this->cooldown->isReady();
    }

    public function remainingSeconds(): float
    {
        return $this->cooldown->remainingSeconds();
    }

    /** Uma tentativa por atualização; tempo excedente não produz ataques retroativos. */
    public function update(float $deltaSeconds): bool
    {
        if ($this->updating) {
            throw new LogicException('Não é permitido atualizar a mesma arma dentro da sua tentativa.');
        }
        $this->updating = true;
        try {
            $this->cooldown->advance($deltaSeconds);
            if (! $this->cooldown->isReady()) {
                return false;
            }
            if (! ($this->execute)($this->definition)) {
                return false;
            }
            $this->cooldown->tryStart();

            return true;
        } finally {
            $this->updating = false;
        }
    }
}
