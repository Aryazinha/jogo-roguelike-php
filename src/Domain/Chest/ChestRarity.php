<?php

declare(strict_types=1);

namespace Jogo\Domain\Chest;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;

/** Uma raridade de baú de chests.json: chance de sair e quantos upgrades dá (RF26). */
final readonly class ChestRarity
{
    public function __construct(
        public string $id,
        public string $name,
        public float $probability,
        public string $rewardType,
        public int $rewardQuantity,
    ) {
        CombatValidation::requiredId($id, 'id');
        CombatValidation::requiredId($name, 'name');
        CombatValidation::requiredId($rewardType, 'rewardType');
        if (! is_finite($probability) || $probability <= 0.0 || $probability > 1.0) {
            throw new InvalidArgumentException('probability deve estar entre 0 (sem incluir) e 1.');
        }
        if ($rewardQuantity < 1) {
            throw new InvalidArgumentException('rewardQuantity deve ser maior ou igual a um.');
        }
    }
}
