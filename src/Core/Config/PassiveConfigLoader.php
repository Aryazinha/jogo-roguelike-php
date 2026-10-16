<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use InvalidArgumentException;
use Jogo\Domain\Progression\ModifierTemplate;
use Jogo\Domain\Progression\PassiveCatalog;
use Jogo\Domain\Progression\PassiveDefinition;
use Jogo\Domain\Value\ModifierType;

/** Lê e valida passives.json; nunca escreve o arquivo de configuração. */
final class PassiveConfigLoader
{
    public function load(string $file): PassiveCatalog
    {
        return $this->map(ConfigJsonReader::load($file), $file);
    }

    public function decode(string $json, string $source = 'passives.json'): PassiveCatalog
    {
        return $this->map(ConfigJsonReader::decode($json, $source), $source);
    }

    private function map(mixed $decoded, string $source): PassiveCatalog
    {
        $root = ConfigSchema::root($decoded, 'itens', 'passives.json', $source);
        $items = ConfigSchema::object($root->itens ?? null, "{$source}: itens");
        $definitions = [];
        foreach (get_object_vars($items) as $id => $raw) {
            $id = (string) $id;
            $path = "{$source}: {$id}";
            $entry = ConfigSchema::object($raw, $path);
            ConfigSchema::fields($entry, ['nome', 'descricao', 'niveisMaximos', 'modificadoresPorNivel'], $path);
            $templates = [];
            foreach (ConfigSchema::list($entry->modificadoresPorNivel ?? null, "{$path}.modificadoresPorNivel") as $index => $rawModifier) {
                $modifierPath = "{$path}.modificadoresPorNivel[{$index}]";
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
                throw new InvalidArgumentException("{$path}.modificadoresPorNivel não pode estar vazio.");
            }
            $definitions[] = new PassiveDefinition($id, ConfigSchema::string($entry, 'nome', $path),
                ConfigSchema::string($entry, 'descricao', $path), ConfigSchema::level($entry, 'niveisMaximos', $path), $templates);
        }
        if ($definitions === []) {
            throw new InvalidArgumentException("{$source}: itens não pode estar vazio.");
        }

        return new PassiveCatalog($definitions);
    }
}
