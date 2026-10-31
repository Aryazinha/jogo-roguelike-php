<?php

declare(strict_types=1);

namespace Jogo\Domain\Chest;

use Jogo\Domain\Value\CombatValidation;

/**
 * O que saiu de um baú aberto. A Frente B aplica (escolhe e melhora as armas);
 * a Frente D mostra. É imutável e leva o ID do baú, para cada recompensa ser
 * rastreável até o baú de onde veio.
 */
final readonly class ChestReward
{
    public function __construct(
        public string $chestId,
        public string $rarityId,
        public string $rewardType,
        public int $quantity,
        public float $openedAtSeconds,
    ) {
        CombatValidation::requiredId($chestId, 'chestId');
        CombatValidation::requiredId($rarityId, 'rarityId');
        CombatValidation::requiredId($rewardType, 'rewardType');
        CombatValidation::nonNegative($openedAtSeconds, 'openedAtSeconds');
    }
}
