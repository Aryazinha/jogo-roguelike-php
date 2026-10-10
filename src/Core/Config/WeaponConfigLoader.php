<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use InvalidArgumentException;
use Jogo\Domain\Weapon\WeaponCatalog;
use JsonException;

/** I/O e decodificação fora do domínio; nunca escreve o arquivo de configuração. */
final class WeaponConfigLoader
{
    public function load(string $file): WeaponCatalog
    {
        if (! is_file($file) || ! is_readable($file)) {
            throw new InvalidArgumentException("{$file}: arquivo de armas inexistente ou sem permissão de leitura.");
        }
        $json = file_get_contents($file);
        if ($json === false) {
            throw new InvalidArgumentException("{$file}: não foi possível ler o arquivo de armas.");
        }

        return $this->decode($json, $file);
    }

    public function decode(string $json, string $source = 'weapons.json'): WeaponCatalog
    {
        try {
            $decoded = json_decode($json, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException("{$source}: JSON malformado: {$exception->getMessage()}", 0, $exception);
        }
        // json_decode sobrescreve chaves repetidas; rejeitá-las evita perder IDs silenciosamente.
        (new JsonDuplicateKeyValidator)->validate($json, $source);

        return (new WeaponDefinitionMapper)->map($decoded, $source);
    }
}
