<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon\Upgrade;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\StatModifier;
use Jogo\Domain\Value\StatResolver;
use Jogo\Domain\Value\Stats;

/** Dados de concessão; level é uma referência de conteúdo, nunca nível controlado pela A. */
final readonly class UpgradeDefinition
{
    /** @param list<StatModifier> $modifiers */
    public function __construct(public string $weaponId, public int $level, public string $description, public array $modifiers)
    {
        CombatValidation::requiredId($weaponId, 'weaponId');
        CombatValidation::requiredId($description, 'description');
        if ($level < 1 || $modifiers === []) {
            throw new InvalidArgumentException('Upgrade exige nível >= 1 e modificadores não vazios.');
        }
        // Também rejeita sources repetidas, sem pressupor valores base ou conceder o efeito.
        (new StatResolver)->resolve(new Stats([]), $modifiers, ignoreMissing: true);
    }
}
