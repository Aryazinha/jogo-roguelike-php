<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use InvalidArgumentException;
use Jogo\Domain\Value\ModifierType;
use Jogo\Domain\Value\StatModifier;
use Jogo\Domain\Weapon\ResolvedWeaponStats;
use Jogo\Domain\Weapon\Upgrade\UpgradeCatalog;
use Jogo\Domain\Weapon\Upgrade\UpgradeDefinition;
use Jogo\Domain\Weapon\WeaponCatalog;

final readonly class UpgradeDefinitionMapper
{
    public function __construct(private WeaponCatalog $weapons) {}

    public function map(mixed $decoded, string $source = 'upgrades.json'): UpgradeCatalog
    {
        $root = ConfigSchema::root($decoded, 'upgrades', 'upgrades.json', $source);
        $groups = ConfigSchema::object($root->upgrades ?? null, "{$source}: upgrades");
        $definitions = [];
        $seen = [];
        foreach (get_object_vars($groups) as $weaponId => $rawLevels) {
            $weaponId = (string) $weaponId;
            $path = "{$source}: {$weaponId}";
            if (! $this->weapons->has($weaponId)) {
                throw new InvalidArgumentException("{$path}.arma referencia uma arma inexistente.");
            }
            $levels = ConfigSchema::list($rawLevels, $path);
            if ($levels === []) {
                throw new InvalidArgumentException("{$path}.nivel: lista de upgrades vazia.");
            }
            foreach ($levels as $index => $raw) {
                $entry = ConfigSchema::object($raw, "{$path}[{$index}]");
                ConfigSchema::fields($entry, ['nivel', 'descricao', 'modificadores'], $path);
                $level = ConfigSchema::level($entry, 'nivel', $path);
                $key = $weaponId.':'.$level;
                if (isset($seen[$key])) {
                    throw new InvalidArgumentException("{$path}.nivel {$level} duplicado.");
                }
                $seen[$key] = true;
                $description = ConfigSchema::string($entry, 'descricao', $path);
                $modifiers = [];
                foreach (ConfigSchema::list($entry->modificadores ?? null, "{$path}.modificadores") as $offset => $rawModifier) {
                    $modifierPath = "{$path}.nivel[{$level}].modificadores[{$offset}]";
                    $modifier = ConfigSchema::object($rawModifier, $modifierPath);
                    ConfigSchema::fields($modifier, ['atributo', 'tipo', 'valor'], $modifierPath);
                    $type = ModifierType::tryFrom(ConfigSchema::string($modifier, 'tipo', $modifierPath))
                        ?? throw new InvalidArgumentException("{$modifierPath}.tipo deve ser FLAT ou PERCENT.");
                    try {
                        $effect = new StatModifier(ConfigSchema::string($modifier, 'atributo', $modifierPath), $type,
                            ConfigSchema::number($modifier, 'valor', $modifierPath), "upgrades:{$weaponId}:{$level}:{$offset}");
                        new ResolvedWeaponStats($this->weapons->get($weaponId), [$effect]);
                    } catch (InvalidArgumentException $error) {
                        throw new InvalidArgumentException("{$modifierPath}.atributo/valor: {$error->getMessage()}", 0, $error);
                    }
                    $modifiers[] = $effect;
                }
                try {
                    new ResolvedWeaponStats($this->weapons->get($weaponId), $modifiers);
                    $definitions[] = new UpgradeDefinition($weaponId, $level, $description, $modifiers);
                } catch (InvalidArgumentException $error) {
                    throw new InvalidArgumentException("{$path}.nivel[{$level}].modificadores: {$error->getMessage()}", 0, $error);
                }
            }
        }
        if ($definitions === []) {
            throw new InvalidArgumentException("{$source}: upgrades não pode estar vazio.");
        }

        return new UpgradeCatalog($definitions);
    }
}
