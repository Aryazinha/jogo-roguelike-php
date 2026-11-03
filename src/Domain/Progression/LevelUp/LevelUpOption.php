<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression\LevelUp;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;

/**
 * Opção imutável apresentada pela Frente D; `level` é o nível que a escolha concede.
 * Uma melhoria com `evolutionId` troca a arma pela evolução especial (RF37) sem mudar o nível.
 */
final readonly class LevelUpOption
{
    public function __construct(
        public LevelUpOptionKind $kind,
        public string $contentId,
        public int $level,
        public bool $requiresReplacement = false,
        public ?string $evolutionId = null,
    ) {
        CombatValidation::requiredId($contentId, 'contentId');
        if ($level < 1) {
            throw new InvalidArgumentException('O nível concedido deve ser >= 1.');
        }
        if ($requiresReplacement && $kind !== LevelUpOptionKind::NEW_WEAPON) {
            throw new InvalidArgumentException('Somente uma arma nova pode exigir substituição.');
        }
        if ($evolutionId !== null && $kind !== LevelUpOptionKind::UPGRADE_WEAPON) {
            throw new InvalidArgumentException('Somente uma melhoria de arma pode ser uma evolução.');
        }
        CombatValidation::optionalId($evolutionId, 'evolutionId');
    }

    public function key(): string
    {
        if ($this->evolutionId !== null) {
            return "{$this->kind->value}:{$this->contentId}:evolucao:{$this->evolutionId}";
        }

        return "{$this->kind->value}:{$this->contentId}:{$this->level}";
    }
}
