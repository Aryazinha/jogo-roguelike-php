<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression\LevelUp;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;

/** Opção imutável apresentada pela Frente D; `level` é o nível que a escolha concede. */
final readonly class LevelUpOption
{
    public function __construct(
        public LevelUpOptionKind $kind,
        public string $contentId,
        public int $level,
        public bool $requiresReplacement = false,
    ) {
        CombatValidation::requiredId($contentId, 'contentId');
        if ($level < 1) {
            throw new InvalidArgumentException('O nível concedido deve ser >= 1.');
        }
        if ($requiresReplacement && $kind !== LevelUpOptionKind::NEW_WEAPON) {
            throw new InvalidArgumentException('Somente uma arma nova pode exigir substituição.');
        }
    }

    public function key(): string
    {
        return "{$this->kind->value}:{$this->contentId}:{$this->level}";
    }
}
