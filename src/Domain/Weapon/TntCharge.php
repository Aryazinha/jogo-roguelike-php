<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Cooldown;
use Jogo\Domain\Value\Vector2;

/** Temporizador lógico por carga; posição selecionada permanece imutável. */
final class TntCharge
{
    private readonly ?Cooldown $timer;

    private bool $detonated = false;

    public function __construct(public readonly Attack $attack, public readonly Vector2 $position)
    {
        $delay = $attack->definition->detonationDelaySeconds;
        if ($attack->definition->shapeId !== 'area' || $delay === null) {
            throw new InvalidArgumentException('Carga exige formato area e atraso definido.');
        }
        $this->timer = $delay === 0.0 ? null : new Cooldown($delay);
        $this->timer?->tryStart();
    }

    public function advance(float $deltaSeconds): void
    {
        CombatValidation::nonNegative($deltaSeconds, 'deltaSeconds');
        if (! $this->detonated) {
            $this->timer?->advance($deltaSeconds);
        }
    }

    public function remainingSeconds(): float
    {
        return $this->timer?->remainingSeconds() ?? 0.0;
    }

    public function isReady(): bool
    {
        return ! $this->detonated && ($this->timer?->isReady() ?? true);
    }

    public function isDetonated(): bool
    {
        return $this->detonated;
    }

    public function detonate(): void
    {
        $this->detonated = true;
    }
}
