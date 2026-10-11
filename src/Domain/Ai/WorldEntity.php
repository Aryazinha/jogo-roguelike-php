<?php

declare(strict_types=1);

namespace Jogo\Domain\Ai;

use Jogo\Domain\Entity\Collidable;
use Jogo\Domain\Value\Vector2;

/** Dados de integração fornecidos pela C; esta entrada não é exposta por WorldView. */
final readonly class WorldEntity
{
    public function __construct(public Collidable $entity, public string $kind, public ?bool $isAlive)
    {
        $this->snapshot();
    }

    public function snapshot(): EntityView
    {
        $position = $this->entity->position();

        return new EntityView($this->entity->id(), new Vector2($position->x(), $position->y()),
            $this->entity->collisionRadius(), $this->kind, $this->isAlive);
    }
}
