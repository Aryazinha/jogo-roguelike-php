<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;

/** Item passivo adquirido no level up (RF21); cada nível concede os mesmos efeitos. */
final readonly class PassiveDefinition
{
    /** @param list<ModifierTemplate> $modifiersPerLevel */
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public int $maxLevel,
        public array $modifiersPerLevel,
    ) {
        CombatValidation::requiredId($id, 'id do passivo');
        CombatValidation::requiredId($name, 'nome do passivo');
        CombatValidation::requiredId($description, 'descricao do passivo');
        if ($maxLevel < 1) {
            throw new InvalidArgumentException('niveisMaximos deve ser um inteiro >= 1.');
        }
        if ($modifiersPerLevel === []) {
            throw new InvalidArgumentException('modificadoresPorNivel não pode estar vazio.');
        }
    }
}
