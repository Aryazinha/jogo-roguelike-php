<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression\Reward;

use InvalidArgumentException;
use Jogo\Domain\Progression\ModifierTemplate;
use Jogo\Domain\Value\CombatValidation;

/** Bônus temporário de drop do cenário (RF38). */
final readonly class TemporaryBonusDefinition
{
    /** @param list<ModifierTemplate> $modifiers */
    public function __construct(
        public string $id,
        public string $name,
        public float $durationSeconds,
        public array $modifiers,
    ) {
        CombatValidation::requiredId($id, 'id do bônus');
        CombatValidation::requiredId($name, 'nome do bônus');
        CombatValidation::positive($durationSeconds, 'duracaoSegundos');
        if ($modifiers === []) {
            throw new InvalidArgumentException('O bônus precisa de pelo menos um modificador.');
        }
    }
}
