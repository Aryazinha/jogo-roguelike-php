<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use InvalidArgumentException;

/** Lê a seção contato de enemies.json: o tempo de invulnerabilidade do jogador. */
final class ContactConfigLoader
{
    public function load(string $file): float
    {
        return $this->map(ConfigJsonReader::load($file), $file);
    }

    public function decode(string $json, string $source = 'enemies.json'): float
    {
        return $this->map(ConfigJsonReader::decode($json, $source), $source);
    }

    private function map(mixed $decoded, string $source): float
    {
        $root = ConfigSchema::object($decoded, $source);
        $path = "{$source}: contato";
        $section = ConfigSchema::object($root->contato ?? null, $path);
        ConfigSchema::fields($section, ['invulnerabilidadeSegundos'], $path);
        ConfigSchema::string($section, '_fonte', $path);
        $seconds = ConfigSchema::number($section, 'invulnerabilidadeSegundos', $path);
        if ($seconds < 0.0) {
            throw new InvalidArgumentException("{$path}.invulnerabilidadeSegundos deve ser maior ou igual a zero.");
        }

        return $seconds;
    }
}
