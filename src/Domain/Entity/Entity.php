<?php

declare(strict_types=1);

namespace Jogo\Domain\Entity;

use InvalidArgumentException;
use Jogo\Domain\Value\Vector2;

/**
 * Base comum para os objetos que ocupam uma posição no mundo do jogo.
 *
 * Vida, comportamento e aparência não pertencem à entidade base: serão
 * adicionados por composição apenas às entidades que realmente precisarem.
 */
abstract class Entity implements Collidable, Movable
{
    private bool $active = true;

    public function __construct(
        private readonly string $id,
        private Vector2 $position,
        private readonly float $collisionRadius,
    ) {
        if (trim($id) === '') {
            throw new InvalidArgumentException('O identificador da entidade não pode estar vazio.');
        }

        if (! is_finite($collisionRadius) || $collisionRadius < 0.0) {
            throw new InvalidArgumentException(
                'O raio de colisão deve ser um número finito maior ou igual a zero.',
            );
        }
    }

    final public function id(): string
    {
        return $this->id;
    }

    final public function position(): Vector2
    {
        return $this->position;
    }

    final public function collisionRadius(): float
    {
        return $this->collisionRadius;
    }

    final public function isActive(): bool
    {
        return $this->active;
    }

    final public function moveTo(Vector2 $position): void
    {
        $this->position = $position;
    }

    final public function translate(Vector2 $displacement): void
    {
        $this->position = $this->position->add($displacement);
    }

    /**
     * Retira a entidade do ciclo de atualização sem destruir o objeto.
     */
    final public function deactivate(): void
    {
        $this->active = false;
    }
}
