<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use Jogo\Domain\Weapon\Upgrade\UpgradeCatalog;
use Jogo\Domain\Weapon\WeaponCatalog;

final readonly class UpgradeConfigLoader
{
    public function __construct(private WeaponCatalog $weapons) {}

    public function load(string $file): UpgradeCatalog
    {
        return (new UpgradeDefinitionMapper($this->weapons))->map(ConfigJsonReader::load($file), $file);
    }

    public function decode(string $json, string $source = 'upgrades.json'): UpgradeCatalog
    {
        return (new UpgradeDefinitionMapper($this->weapons))->map(ConfigJsonReader::decode($json, $source), $source);
    }
}
