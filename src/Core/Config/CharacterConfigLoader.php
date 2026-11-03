<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use Jogo\Domain\Character\CharacterCatalog;
use Jogo\Domain\Weapon\WeaponCatalog;

/** Lê characters.json fora do domínio; nunca escreve o arquivo de configuração. */
final readonly class CharacterConfigLoader
{
    public function __construct(private WeaponCatalog $weapons) {}

    public function load(string $file): CharacterCatalog
    {
        return (new CharacterDefinitionMapper($this->weapons))->map(ConfigJsonReader::load($file), $file);
    }

    public function decode(string $json, string $source = 'characters.json'): CharacterCatalog
    {
        return (new CharacterDefinitionMapper($this->weapons))->map(ConfigJsonReader::decode($json, $source), $source);
    }
}
