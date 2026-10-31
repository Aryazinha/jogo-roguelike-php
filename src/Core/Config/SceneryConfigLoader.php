<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use InvalidArgumentException;
use Jogo\Domain\Scenery\BreakableType;
use Jogo\Domain\Scenery\DropChance;
use Jogo\Domain\Scenery\ScenerySettings;
use Jogo\Domain\Scenery\TemporaryBonus;
use Jogo\Domain\Value\ModifierType;
use Jogo\Domain\Value\StatModifier;
use stdClass;

/** Lê scenery.json: densidade, elementos quebráveis e bônus temporários. */
final class SceneryConfigLoader
{
    public function load(string $file): ScenerySettings
    {
        return $this->map(ConfigJsonReader::load($file), $file);
    }

    public function decode(string $json, string $source = 'scenery.json'): ScenerySettings
    {
        return $this->map(ConfigJsonReader::decode($json, $source), $source);
    }

    private function map(mixed $decoded, string $source): ScenerySettings
    {
        $root = ConfigSchema::object($decoded, $source);
        ConfigSchema::fields($root, ['densidadePorMil', 'elementos', 'bonusTemporarios'], $source);
        $meta = ConfigSchema::object($root->_meta ?? null, "{$source}: _meta");
        if (ConfigSchema::string($meta, 'arquivo', "{$source}: _meta") !== 'scenery.json') {
            throw new InvalidArgumentException("{$source}: _meta.arquivo deve ser scenery.json.");
        }

        $types = [];
        foreach (get_object_vars(ConfigSchema::object($root->elementos ?? null, "{$source}: elementos")) as $id => $raw) {
            $id = (string) $id;
            $types[] = $this->type($id, ConfigSchema::object($raw, "{$source}: {$id}"), "{$source}: {$id}");
        }
        $bonuses = [];
        foreach (get_object_vars(ConfigSchema::object($root->bonusTemporarios ?? null, "{$source}: bonusTemporarios")) as $id => $raw) {
            $id = (string) $id;
            $bonuses[] = $this->bonus($id, ConfigSchema::object($raw, "{$source}: {$id}"), "{$source}: {$id}");
        }

        try {
            return new ScenerySettings(ConfigSchema::number($root, 'densidadePorMil', $source), $types, $bonuses);
        } catch (InvalidArgumentException $error) {
            if (str_starts_with($error->getMessage(), $source)) {
                throw $error;
            }
            throw new InvalidArgumentException("{$source}: {$error->getMessage()}", 0, $error);
        }
    }

    private function type(string $id, stdClass $entry, string $path): BreakableType
    {
        ConfigSchema::fields($entry, ['nome', 'vida', 'raioColisao', 'bloqueiaMovimento', 'drops'], $path);
        $blocks = $entry->bloqueiaMovimento ?? null;
        if (! is_bool($blocks)) {
            throw new InvalidArgumentException("{$path}.bloqueiaMovimento deve ser true ou false.");
        }

        try {
            $drops = [];
            foreach (ConfigSchema::list($entry->drops ?? null, "{$path}.drops") as $index => $raw) {
                $drop = ConfigSchema::object($raw, "{$path}.drops[{$index}]");
                ConfigSchema::fields($drop, ['item', 'probabilidade'], "{$path}.drops[{$index}]");
                $drops[] = new DropChance(ConfigSchema::string($drop, 'item', "{$path}.drops[{$index}]"),
                    ConfigSchema::number($drop, 'probabilidade', "{$path}.drops[{$index}]"));
            }

            return new BreakableType($id, ConfigSchema::string($entry, 'nome', $path), ConfigSchema::number($entry, 'vida', $path),
                ConfigSchema::number($entry, 'raioColisao', $path), $blocks, $drops);
        } catch (InvalidArgumentException $error) {
            if (str_starts_with($error->getMessage(), $path)) {
                throw $error;
            }
            throw new InvalidArgumentException("{$path}: {$error->getMessage()}", 0, $error);
        }
    }

    private function bonus(string $id, stdClass $entry, string $path): TemporaryBonus
    {
        ConfigSchema::fields($entry, ['nome', 'duracaoSegundos', 'modificadores'], $path);
        $modifiers = [];
        foreach (ConfigSchema::list($entry->modificadores ?? null, "{$path}.modificadores") as $index => $raw) {
            $modifierPath = "{$path}.modificadores[{$index}]";
            $modifier = ConfigSchema::object($raw, $modifierPath);
            ConfigSchema::fields($modifier, ['atributo', 'tipo', 'valor'], $modifierPath);
            $type = ModifierType::tryFrom(ConfigSchema::string($modifier, 'tipo', $modifierPath))
                ?? throw new InvalidArgumentException("{$modifierPath}.tipo deve ser FLAT ou PERCENT.");
            try {
                $modifiers[] = new StatModifier(ConfigSchema::string($modifier, 'atributo', $modifierPath), $type,
                    ConfigSchema::number($modifier, 'valor', $modifierPath), "cenario:{$id}:{$index}");
            } catch (InvalidArgumentException $error) {
                throw new InvalidArgumentException("{$modifierPath}: {$error->getMessage()}", 0, $error);
            }
        }

        try {
            return new TemporaryBonus($id, ConfigSchema::string($entry, 'nome', $path),
                ConfigSchema::number($entry, 'duracaoSegundos', $path), $modifiers);
        } catch (InvalidArgumentException $error) {
            if (str_starts_with($error->getMessage(), $path)) {
                throw $error;
            }
            throw new InvalidArgumentException("{$path}: {$error->getMessage()}", 0, $error);
        }
    }
}
