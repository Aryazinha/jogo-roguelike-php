<?php

declare(strict_types=1);

namespace Jogo\Domain\Character;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\ModifierType;
use Jogo\Domain\Value\Stats;

/** Definição da passiva única do personagem (RF39); a concessão por nível pertence à B4. */
final readonly class CharacterPassive
{
    public function __construct(
        public string $id,
        public string $description,
        public int $everyLevels,
        public string $attribute,
        public ModifierType $type,
        public float $value,
    ) {
        CombatValidation::requiredId($id, 'id da passiva');
        CombatValidation::requiredId($description, 'descricao da passiva');
        if ($everyLevels < 1) {
            throw new InvalidArgumentException('aCadaNiveis deve ser um inteiro >= 1.');
        }
        Stats::assertAttribute($attribute);
        if (! is_finite($value) || $value === 0.0) {
            throw new InvalidArgumentException('O valor da passiva deve ser finito e diferente de zero.');
        }
    }
}
