<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\Targeting\TargetingResult;

/** Ponto lógico com trajetória fixa; o snapshot do alvo não acompanha movimentos. */
final class LogicalProjectile
{
    private Vector2 $position;

    private float $traveled = 0.0;

    private float $correction = 0.0;

    private bool $active = true;

    public function __construct(public readonly Attack $attack, public readonly Vector2 $origin, public readonly TargetingResult $target)
    {
        if ($attack->definition->shapeId !== 'projetil' || $target->direction->length() === 0.0) {
            throw new InvalidArgumentException('Projétil exige formato projetil e direção não nula.');
        }
        $this->position = $origin;
    }

    public function position(): Vector2
    {
        return $this->position;
    }

    public function traveled(): float
    {
        return $this->traveled;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function advance(float $deltaSeconds): void
    {
        CombatValidation::nonNegative($deltaSeconds, 'deltaSeconds');
        if (! $this->active || $deltaSeconds === 0.0) {
            return;
        }
        $definition = $this->attack->definition;
        $speed = $definition->projectileSpeed;
        if ($speed === null) {
            throw new InvalidArgumentException('projectileSpeed ausente.');
        }
        $remaining = $definition->range - $this->traveled;
        if ($deltaSeconds >= $remaining / $speed) {
            $next = $definition->range;
            $correction = 0.0;
        } else {
            $adjusted = $speed * $deltaSeconds - $this->correction;
            $next = min($definition->range, $this->traveled + $adjusted);
            $correction = ($next - $this->traveled) - $adjusted;
        }
        $position = $this->origin->add($this->target->direction->scale($next));
        $this->traveled = $next;
        $this->correction = $correction;
        $this->position = $position;
    }

    public function hasReachedRange(): bool
    {
        return $this->traveled >= $this->attack->definition->range;
    }

    public function consume(): void
    {
        $this->active = false;
    }
}
