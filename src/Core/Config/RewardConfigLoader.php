<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use InvalidArgumentException;
use Jogo\Domain\Progression\ModifierTemplate;
use Jogo\Domain\Progression\Reward\HealingItem;
use Jogo\Domain\Progression\Reward\TemporaryBonusCatalog;
use Jogo\Domain\Progression\Reward\TemporaryBonusDefinition;
use Jogo\Domain\Value\ModifierType;

/**
 * Lê só o que a B aplica: `cura` de chests.json e `bonusTemporarios` de scenery.json.
 * Raridades, elementos e drops continuam sendo validados pela Frente C.
 */
final class RewardConfigLoader
{
    public function healingItem(string $chestsFile): HealingItem
    {
        $root = ConfigSchema::object(ConfigJsonReader::load($chestsFile), $chestsFile);
        $path = "{$chestsFile}: cura";
        $entry = ConfigSchema::object($root->cura ?? null, $path);
        ConfigSchema::fields($entry, ['id', 'nome', 'cura'], $path);
        $amount = ConfigSchema::number($entry, 'cura', $path);
        if ($amount <= 0.0) {
            throw new InvalidArgumentException("{$path}.cura deve ser maior que zero.");
        }

        return new HealingItem(ConfigSchema::string($entry, 'id', $path), ConfigSchema::string($entry, 'nome', $path), $amount);
    }

    public function temporaryBonuses(string $sceneryFile): TemporaryBonusCatalog
    {
        $root = ConfigSchema::object(ConfigJsonReader::load($sceneryFile), $sceneryFile);
        $bonuses = ConfigSchema::object($root->bonusTemporarios ?? null, "{$sceneryFile}: bonusTemporarios");
        $definitions = [];
        foreach (get_object_vars($bonuses) as $id => $raw) {
            $id = (string) $id;
            $path = "{$sceneryFile}: {$id}";
            $entry = ConfigSchema::object($raw, $path);
            ConfigSchema::fields($entry, ['nome', 'duracaoSegundos', 'modificadores'], $path);
            $duration = ConfigSchema::number($entry, 'duracaoSegundos', $path);
            if ($duration <= 0.0) {
                throw new InvalidArgumentException("{$path}.duracaoSegundos deve ser maior que zero.");
            }
            $templates = [];
            foreach (ConfigSchema::list($entry->modificadores ?? null, "{$path}.modificadores") as $index => $rawModifier) {
                $modifierPath = "{$path}.modificadores[{$index}]";
                $modifier = ConfigSchema::object($rawModifier, $modifierPath);
                ConfigSchema::fields($modifier, ['atributo', 'tipo', 'valor'], $modifierPath);
                $type = ModifierType::tryFrom(ConfigSchema::string($modifier, 'tipo', $modifierPath))
                    ?? throw new InvalidArgumentException("{$modifierPath}.tipo deve ser FLAT ou PERCENT.");
                try {
                    $templates[] = new ModifierTemplate(ConfigSchema::string($modifier, 'atributo', $modifierPath),
                        $type, ConfigSchema::number($modifier, 'valor', $modifierPath));
                } catch (InvalidArgumentException $error) {
                    if (str_starts_with($error->getMessage(), $modifierPath)) {
                        throw $error;
                    }
                    throw new InvalidArgumentException("{$modifierPath}: {$error->getMessage()}", 0, $error);
                }
            }
            if ($templates === []) {
                throw new InvalidArgumentException("{$path}.modificadores não pode estar vazio.");
            }
            $definitions[] = new TemporaryBonusDefinition($id, ConfigSchema::string($entry, 'nome', $path), $duration, $templates);
        }

        return new TemporaryBonusCatalog($definitions);
    }
}
