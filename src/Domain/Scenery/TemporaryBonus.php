<?php

declare(strict_types=1);

namespace Jogo\Domain\Scenery;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\StatModifier;

/**
 * Bônus temporário de scenery.json: Fúria, Pressa ou Agilidade.
 *
 * Usa o StatModifier da A, com os mesmos atributos de Stats, para a Frente B
 * aplicar no jogador sem tradução.
 */
final readonly class TemporaryBonus
{
    /** @param list<StatModifier> $modifiers */
    public function __construct(
        public string $id,
        public string $name,
        public float $durationSeconds,
        public array $modifiers,
    ) {
        CombatValidation::requiredId($id, 'id');
        CombatValidation::requiredId($name, 'name');
        CombatValidation::positive($durationSeconds, 'durationSeconds');
        if ($modifiers === []) {
            throw new InvalidArgumentException('um bônus precisa de pelo menos um modificador.');
        }
    }
}
