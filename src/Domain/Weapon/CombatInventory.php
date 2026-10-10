<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;
use LengthException;
use LogicException;
use OutOfBoundsException;

/** Slots de combate, sem critérios de concessão ou substituição da progressão. */
final class CombatInventory
{
    public const DEFAULT_CAPACITY = 2;

    /** @var list<Weapon> */
    private array $weapons = [];

    private bool $updating = false;

    public function __construct(private readonly int $capacity = self::DEFAULT_CAPACITY)
    {
        if ($capacity < 1) {
            throw new InvalidArgumentException('A capacidade deve ser maior que zero.');
        }
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function count(): int
    {
        return count($this->weapons);
    }

    /** @return list<Weapon> */
    public function weapons(): array
    {
        return $this->weapons;
    }

    public function at(int $slot): ?Weapon
    {
        $this->assertSlot($slot);

        return $this->weapons[$slot] ?? null;
    }

    public function find(string $id): ?Weapon
    {
        CombatValidation::requiredId($id, 'id');
        foreach ($this->weapons as $weapon) {
            if ($weapon->definition()->id === $id) {
                return $weapon;
            }
        }

        return null;
    }

    /** @param callable(WeaponDefinition): bool $execute */
    public function add(WeaponDefinition $definition, callable $execute): int
    {
        $this->assertNewId($definition->id);
        if ($this->count() === $this->capacity) {
            throw new LengthException('O inventário de combate atingiu sua capacidade.');
        }
        $slot = $this->count();
        $this->weapons[] = new Weapon($definition, $execute);

        return $slot;
    }

    /** @param callable(WeaponDefinition): bool $execute */
    public function replace(int $slot, WeaponDefinition $definition, callable $execute): void
    {
        if ($this->at($slot) === null) {
            throw new OutOfBoundsException("O slot {$slot} está vazio.");
        }
        // Inclusive o mesmo ID do próprio slot: não reinicia cooldown silenciosamente.
        $this->assertNewId($definition->id);
        array_splice($this->weapons, $slot, 1, [new Weapon($definition, $execute)]);
    }

    /** Atualiza um snapshot dos slots em ordem e retorna a quantidade de execuções aceitas. */
    public function update(float $deltaSeconds): int
    {
        CombatValidation::nonNegative($deltaSeconds, 'deltaSeconds');
        if ($this->updating) {
            throw new LogicException('Não é permitido atualizar o inventário dentro de uma tentativa.');
        }
        $this->updating = true;
        $accepted = 0;
        $current = $this->weapons;
        try {
            foreach ($current as $weapon) {
                if ($weapon->update($deltaSeconds)) {
                    $accepted++;
                }
            }

            return $accepted;
        } finally {
            $this->updating = false;
        }
    }

    private function assertSlot(int $slot): void
    {
        if ($slot < 0 || $slot >= $this->capacity) {
            throw new OutOfBoundsException("Slot {$slot} fora da capacidade do inventário.");
        }
    }

    private function assertNewId(string $id): void
    {
        if ($this->find($id) !== null) {
            throw new InvalidArgumentException("A arma {$id} já está equipada.");
        }
    }
}
