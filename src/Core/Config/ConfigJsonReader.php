<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use InvalidArgumentException;
use JsonException;

/** Leitura comum da A8, com a mesma detecção de chaves duplicadas da A6. */
final class ConfigJsonReader
{
    public static function load(string $file): mixed
    {
        if (! is_file($file) || ! is_readable($file)) {
            throw new InvalidArgumentException("{$file}: arquivo inexistente ou sem permissão de leitura.");
        }
        $json = file_get_contents($file);
        if ($json === false) {
            throw new InvalidArgumentException("{$file}: não foi possível ler o arquivo.");
        }

        return self::decode($json, $file);
    }

    public static function decode(string $json, string $source): mixed
    {
        try {
            $decoded = json_decode($json, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new InvalidArgumentException("{$source}: JSON malformado: {$error->getMessage()}", 0, $error);
        }
        (new JsonDuplicateKeyValidator)->validate($json, $source);

        return $decoded;
    }
}
