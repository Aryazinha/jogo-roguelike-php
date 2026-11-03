<?php

declare(strict_types=1);

namespace Jogo\Domain\Scenery;

use InvalidArgumentException;

/**
 * Valor dos itens de cenário que não são Steak nem bônus (RF38).
 *
 * O XP pequeno entra pelo collectXp() da Frente B e o item especial dá
 * upgrades de arma pelo mesmo serviço dos baús (grantWeaponUpgrades()).
 */
final readonly class SceneryItems
{
    public function __construct(
        public string $smallXpName,
        public int $smallXp,
        public string $specialItemName,
        public int $specialItemUpgrades,
    ) {
        if (trim($smallXpName) === '' || trim($specialItemName) === '') {
            throw new InvalidArgumentException('os itens de cenário precisam de nome.');
        }
        if ($smallXp < 1) {
            throw new InvalidArgumentException('o XP pequeno deve valer pelo menos 1.');
        }
        if ($specialItemUpgrades < 1) {
            throw new InvalidArgumentException('o item especial deve dar pelo menos 1 upgrade de arma.');
        }
    }
}
