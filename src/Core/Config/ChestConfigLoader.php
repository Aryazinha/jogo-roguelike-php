<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use InvalidArgumentException;
use Jogo\Domain\Chest\ChestRarity;
use Jogo\Domain\Chest\ChestSettings;
use Jogo\Domain\Chest\HealingItem;
use stdClass;

/** Lê chests.json: geração, raridades e item de cura. */
final class ChestConfigLoader
{
    public function load(string $file): ChestSettings
    {
        return $this->map(ConfigJsonReader::load($file), $file);
    }

    public function decode(string $json, string $source = 'chests.json'): ChestSettings
    {
        return $this->map(ConfigJsonReader::decode($json, $source), $source);
    }

    private function map(mixed $decoded, string $source): ChestSettings
    {
        $root = ConfigSchema::object($decoded, $source);
        ConfigSchema::fields($root, ['geracao', 'raridades', 'cura'], $source);
        $meta = ConfigSchema::object($root->_meta ?? null, "{$source}: _meta");
        if (ConfigSchema::string($meta, 'arquivo', "{$source}: _meta") !== 'chests.json') {
            throw new InvalidArgumentException("{$source}: _meta.arquivo deve ser chests.json.");
        }

        $path = "{$source}: geracao";
        $generation = ConfigSchema::object($root->geracao ?? null, $path);
        ConfigSchema::fields($generation, ['bausAoDerrotarBoss', 'chancePorInimigoAbatido'], $path);
        ConfigSchema::string($generation, '_fonte', $path);
        $onBoss = $generation->bausAoDerrotarBoss ?? null;
        if (! is_int($onBoss)) {
            throw new InvalidArgumentException("{$path}.bausAoDerrotarBoss deve ser inteiro.");
        }

        $rarities = [];
        foreach (ConfigSchema::list($root->raridades ?? null, "{$source}: raridades") as $index => $raw) {
            $rarities[] = $this->rarity(ConfigSchema::object($raw, "{$source}: raridades[{$index}]"), "{$source}: raridades[{$index}]");
        }

        $curePath = "{$source}: cura";
        $cure = ConfigSchema::object($root->cura ?? null, $curePath);
        ConfigSchema::fields($cure, ['id', 'nome', 'cura'], $curePath);

        try {
            return new ChestSettings($rarities, $onBoss, ConfigSchema::number($generation, 'chancePorInimigoAbatido', $path),
                new HealingItem(ConfigSchema::string($cure, 'id', $curePath), ConfigSchema::string($cure, 'nome', $curePath),
                    ConfigSchema::number($cure, 'cura', $curePath)));
        } catch (InvalidArgumentException $error) {
            if (str_starts_with($error->getMessage(), $source)) {
                throw $error;
            }
            throw new InvalidArgumentException("{$source}: {$error->getMessage()}", 0, $error);
        }
    }

    private function rarity(stdClass $entry, string $path): ChestRarity
    {
        ConfigSchema::fields($entry, ['id', 'nome', 'probabilidade', 'recompensa'], $path);
        ConfigSchema::string($entry, '_fonte', $path);
        $reward = ConfigSchema::object($entry->recompensa ?? null, "{$path}.recompensa");
        ConfigSchema::fields($reward, ['tipo', 'quantidade'], "{$path}.recompensa");
        $quantity = $reward->quantidade ?? null;
        if (! is_int($quantity)) {
            throw new InvalidArgumentException("{$path}.recompensa.quantidade deve ser inteiro.");
        }

        try {
            return new ChestRarity(ConfigSchema::string($entry, 'id', $path), ConfigSchema::string($entry, 'nome', $path),
                ConfigSchema::number($entry, 'probabilidade', $path),
                ConfigSchema::string($reward, 'tipo', "{$path}.recompensa"), $quantity);
        } catch (InvalidArgumentException $error) {
            if (str_starts_with($error->getMessage(), $path)) {
                throw $error;
            }
            throw new InvalidArgumentException("{$path}: {$error->getMessage()}", 0, $error);
        }
    }
}
