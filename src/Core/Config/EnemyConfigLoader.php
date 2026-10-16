<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use Jogo\Domain\Enemy\EnemyCatalog;

/** Lê enemies.json com detecção de chaves duplicadas; o domínio só recebe o catálogo. */
final class EnemyConfigLoader
{
    public function load(string $file): EnemyCatalog
    {
        return (new EnemyDefinitionMapper)->map(ConfigJsonReader::load($file), $file);
    }

    public function decode(string $json, string $source = 'enemies.json'): EnemyCatalog
    {
        return (new EnemyDefinitionMapper)->map(ConfigJsonReader::decode($json, $source), $source);
    }
}
