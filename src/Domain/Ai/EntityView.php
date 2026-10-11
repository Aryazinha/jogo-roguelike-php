<?php

declare(strict_types=1);

namespace Jogo\Domain\Ai;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Vector2;

/** Fotografia de leitura; nunca contém uma entidade ou componente de vida mutável. */
final readonly class EntityView
{
    public function __construct(
        public string $id,
        public Vector2 $position,
        public float $collisionRadius,
        public string $kind,
        public ?bool $isAlive,
    ) {
        CombatValidation::requiredId($id, 'id');
        CombatValidation::nonNegative($collisionRadius, 'collisionRadius');
        if (! in_array($kind, ['player', 'enemy', 'projectile', 'chest', 'pickup', 'breakable'], true)) {
            throw new InvalidArgumentException('kind desconhecido.');
        }
    }
}
