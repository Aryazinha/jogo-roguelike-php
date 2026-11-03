<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use InvalidArgumentException;
use Jogo\Domain\Ai\Behavior\SteeringSettings;

/** Lê a seção separacao de enemies.json. */
final class SteeringConfigLoader
{
    private const FIELDS = ['raioPixels', 'pesoPerseguir', 'pesoSeparacao'];

    public function load(string $file): SteeringSettings
    {
        return $this->map(ConfigJsonReader::load($file), $file);
    }

    public function decode(string $json, string $source = 'enemies.json'): SteeringSettings
    {
        return $this->map(ConfigJsonReader::decode($json, $source), $source);
    }

    private function map(mixed $decoded, string $source): SteeringSettings
    {
        $root = ConfigSchema::object($decoded, $source);
        $path = "{$source}: separacao";
        $section = ConfigSchema::object($root->separacao ?? null, $path);
        ConfigSchema::fields($section, self::FIELDS, $path);
        ConfigSchema::string($section, '_fonte', $path);

        try {
            return new SteeringSettings(
                ConfigSchema::number($section, 'raioPixels', $path),
                ConfigSchema::number($section, 'pesoPerseguir', $path),
                ConfigSchema::number($section, 'pesoSeparacao', $path),
            );
        } catch (InvalidArgumentException $error) {
            if (str_starts_with($error->getMessage(), $path)) {
                throw $error;
            }
            $message = strtr($error->getMessage(), ['separationRadius' => 'raioPixels',
                'separationWeight' => 'pesoSeparacao', 'seekWeight' => 'pesoPerseguir']);
            throw new InvalidArgumentException("{$path}: {$message}", 0, $error);
        }
    }
}
