<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use InvalidArgumentException;
use Jogo\Domain\World\MapLayout;

/** Lê map.json e entrega o MapLayout; erros do domínio saem com os nomes do JSON. */
final class MapConfigLoader
{
    private const FIELDS = ['larguraPixels', 'alturaPixels', 'margemBordaPixels',
        'distanciaMinimaSpawnPixels', 'distanciaMaximaSpawnPixels'];

    private const JSON_NAMES = [
        'width' => 'larguraPixels', 'height' => 'alturaPixels', 'borderMargin' => 'margemBordaPixels',
        'spawnMinDistance' => 'distanciaMinimaSpawnPixels', 'spawnMaxDistance' => 'distanciaMaximaSpawnPixels',
    ];

    public function load(string $file): MapLayout
    {
        return $this->map(ConfigJsonReader::load($file), $file);
    }

    public function decode(string $json, string $source = 'map.json'): MapLayout
    {
        return $this->map(ConfigJsonReader::decode($json, $source), $source);
    }

    private function map(mixed $decoded, string $source): MapLayout
    {
        $root = ConfigSchema::root($decoded, 'mapa', 'map.json', $source);
        $path = "{$source}: mapa";
        $map = ConfigSchema::object($root->mapa ?? null, $path);
        ConfigSchema::fields($map, self::FIELDS, $path);
        ConfigSchema::string($map, '_fonte', $path);

        $values = [];
        foreach (self::FIELDS as $field) {
            $values[] = ConfigSchema::number($map, $field, $path);
        }

        try {
            return new MapLayout(...$values);
        } catch (InvalidArgumentException $error) {
            throw new InvalidArgumentException("{$path}: ".strtr($error->getMessage(), self::JSON_NAMES), 0, $error);
        }
    }
}
