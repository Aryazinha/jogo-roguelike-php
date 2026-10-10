<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon;

use Closure;
use Jogo\Domain\Value\Cooldown;
use LogicException;

/** Execução genérica: cada instância possui seu próprio cooldown. */
final class Weapon
{
    private readonly Cooldown $cooldown;

    /** @var Closure(WeaponDefinition): bool */
    private readonly Closure $execute;

    private bool $updating = false;

    /** @param callable(WeaponDefinition): bool $execute Retorna se a tentativa foi aceita. */
    public function __construct(private readonly WeaponDefinition $definition, callable $execute)
    {
        $this->cooldown = new Cooldown($definition->cooldownSeconds);
        $this->execute = Closure::fromCallable($execute);
    }

    public function definition(): WeaponDefinition
    {
        return $this->definition;
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
