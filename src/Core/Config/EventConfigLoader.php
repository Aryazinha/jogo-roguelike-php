<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use InvalidArgumentException;
use Jogo\Domain\RandomEvent\EventEffect;
use Jogo\Domain\RandomEvent\EventSettings;
use Jogo\Domain\RandomEvent\RandomEventDefinition;
use stdClass;

/** Lê events.json: intervalo entre eventos e os eventos com peso, duração e efeitos. */
final class EventConfigLoader
{
    public function load(string $file): EventSettings
    {
        return $this->map(ConfigJsonReader::load($file), $file);
    }

    public function decode(string $json, string $source = 'events.json'): EventSettings
    {
        return $this->map(ConfigJsonReader::decode($json, $source), $source);
    }

    private function map(mixed $decoded, string $source): EventSettings
    {
        $root = ConfigSchema::object($decoded, $source);
        ConfigSchema::fields($root, ['intervaloEntreEventosSegundos', 'eventos'], $source);
        $meta = ConfigSchema::object($root->_meta ?? null, "{$source}: _meta");
        if (ConfigSchema::string($meta, 'arquivo', "{$source}: _meta") !== 'events.json') {
            throw new InvalidArgumentException("{$source}: _meta.arquivo deve ser events.json.");
        }

        $gapPath = "{$source}: intervaloEntreEventosSegundos";
        $gap = ConfigSchema::object($root->intervaloEntreEventosSegundos ?? null, $gapPath);
        ConfigSchema::fields($gap, ['min', 'max'], $gapPath);
        $min = $gap->min ?? null;
        $max = $gap->max ?? null;
        if (! is_int($min) || ! is_int($max)) {
            throw new InvalidArgumentException("{$gapPath}.min e .max devem ser inteiros.");
        }

        $events = [];
        foreach (get_object_vars(ConfigSchema::object($root->eventos ?? null, "{$source}: eventos")) as $id => $raw) {
            $id = (string) $id;
            $events[] = $this->event($id, ConfigSchema::object($raw, "{$source}: {$id}"), "{$source}: {$id}");
        }

        try {
            return new EventSettings($min, $max, $events);
        } catch (InvalidArgumentException $error) {
            throw new InvalidArgumentException("{$source}: {$error->getMessage()}", 0, $error);
        }
    }

    private function event(string $id, stdClass $entry, string $path): RandomEventDefinition
    {
        ConfigSchema::fields($entry, ['nome', 'descricao', 'peso', 'duracaoSegundos', 'efeitos'], $path);
        ConfigSchema::string($entry, '_fonte', $path);
        $weight = $entry->peso ?? null;
        if (! is_int($weight)) {
            throw new InvalidArgumentException("{$path}.peso deve ser inteiro.");
        }

        $effects = [];
        foreach (ConfigSchema::list($entry->efeitos ?? null, "{$path}.efeitos") as $index => $raw) {
            $effectPath = "{$path}.efeitos[{$index}]";
            $effect = ConfigSchema::object($raw, $effectPath);
            ConfigSchema::fields($effect, ['atributo', 'tipo', 'valor'], $effectPath);
            if (ConfigSchema::string($effect, 'tipo', $effectPath) !== 'PERCENT') {
                throw new InvalidArgumentException("{$effectPath}.tipo deve ser PERCENT.");
            }
            try {
                $effects[] = new EventEffect(ConfigSchema::string($effect, 'atributo', $effectPath),
                    ConfigSchema::number($effect, 'valor', $effectPath));
            } catch (InvalidArgumentException $error) {
                throw new InvalidArgumentException("{$effectPath}: {$error->getMessage()}", 0, $error);
            }
        }

        try {
            return new RandomEventDefinition($id, ConfigSchema::string($entry, 'nome', $path),
                ConfigSchema::string($entry, 'descricao', $path), $weight,
                ConfigSchema::number($entry, 'duracaoSegundos', $path), $effects);
        } catch (InvalidArgumentException $error) {
            if (str_starts_with($error->getMessage(), $path)) {
                throw $error;
            }
            throw new InvalidArgumentException("{$path}: {$error->getMessage()}", 0, $error);
        }
    }
}
