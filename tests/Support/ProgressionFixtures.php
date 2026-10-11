<?php

declare(strict_types=1);

namespace Jogo\Tests\Support;

use Jogo\Core\Config\EvolutionConfigLoader;
use Jogo\Core\Config\UpgradeConfigLoader;
use Jogo\Domain\Weapon\Upgrade\EvolutionCatalog;
use Jogo\Domain\Weapon\Upgrade\UpgradeCatalog;
use Jogo\Domain\Weapon\Upgrade\WeaponProgressionService;
use Jogo\Domain\Weapon\WeaponCatalog;

final class ProgressionFixtures
{
    public static function upgrades(WeaponCatalog $weapons): UpgradeCatalog
    {
        return (new UpgradeConfigLoader($weapons))->load(dirname(__DIR__, 2).'/config/upgrades.json');
    }

    public static function evolutions(WeaponCatalog $weapons): EvolutionCatalog
    {
        // IDs de conteúdo existentes; esta fixture não implementa nem concede passivos.
        return (new EvolutionConfigLoader($weapons, ['sharpness', 'gunpowder']))->load(dirname(__DIR__, 2).'/config/evolutions.json');
    }

    public static function service(CombatHarness $h): WeaponProgressionService
    {
        return new WeaponProgressionService($h->inventory, self::evolutions($h->catalog), $h->combat);
    }
}
