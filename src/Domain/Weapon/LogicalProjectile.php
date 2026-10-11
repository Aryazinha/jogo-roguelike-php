<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\Targeting\TargetingResult;
use LogicException;

/**
 * Ponto reutilizável; referências de leitura só são válidas durante a aquisição.
 *
 * @property-read Attack $attack
 * @property-read Vector2 $origin
 * @property-read TargetingResult $target
 */
final class LogicalProjectile
{
    private ?Attack $currentAttack = null;

    private ?Vector2 $currentOrigin = null;

    private ?TargetingResult $currentTarget = null;

    private Vector2 $position;

    private float $traveled = 0.0;

    private float $correction = 0.0;

    private bool $active = false;

    public function __construct(?Attack $attack = null, ?Vector2 $origin = null, ?TargetingResult $target = null)
    {
        $this->position = Vector2::zero();
        if ($attack !== null && $origin !== null && $target !== null) {
            $this->initialize($attack, $origin, $target);
            $this->activate();
        } elseif ($attack !== null || $origin !== null || $target !== null) {
            throw new InvalidArgumentException('Forneça ataque, origem e alvo juntos.');
        }
    }

    public function initialize(Attack $attack, Vector2 $origin, TargetingResult $target): void
    {
        if ($this->currentAttack !== null) {
            throw new LogicException('Projétil deve ser liberado antes de reinicializar.');
        }
        if ($attack->definition->shapeId !== 'projetil' || $target->direction->length() === 0.0) {
            throw new InvalidArgumentException('Projétil exige formato projetil e direção não nula.');
        }
        $this->position = $origin;
        $this->currentAttack = $attack;
        $this->currentOrigin = $origin;
        $this->currentTarget = $target;
    }

    public function activate(): void
    {
        if ($this->currentAttack === null || $this->active) {
            throw new LogicException('Ativação exige projétil inicializado e inativo.');
        }
        $this->active = true;
    }

    public function reset(): void
    {
        $this->active = false;
        $this->currentAttack = null;
        $this->currentOrigin = null;
        $this->currentTarget = null;
        $this->position = Vector2::zero();
        $this->traveled = 0.0;
        $this->correction = 0.0;
    }

    /** Preserva a leitura pública da A7, sem permitir escrita externa. */
    public function __get(string $name): Attack|Vector2|TargetingResult
    {
        return match ($name) {
            'attack' => $this->currentAttack,
            'origin' => $this->currentOrigin,
            'target' => $this->currentTarget,
            default => null,
        } ?? throw new LogicException('Referência indisponível em projétil liberado: '.$name);
    }

    public function __set(string $name, mixed $value): void
    {
        throw new LogicException('As referências do projétil são somente leitura: '.$name);
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
        return $this->currentAttack !== null && $this->traveled >= $this->currentAttack->definition->range;
    }

    public function consume(): void
    {
        $this->active = false;
    }
}
