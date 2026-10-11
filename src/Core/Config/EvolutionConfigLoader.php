<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use Jogo\Domain\Weapon\Upgrade\EvolutionCatalog;
use Jogo\Domain\Weapon\WeaponCatalog;

final readonly class EvolutionConfigLoader
{
    /** @param list<string> $passiveIds IDs disponíveis, fornecidos pela B/composição. */
    public function __construct(private WeaponCatalog $weapons, private array $passiveIds) {}

    public function load(string $file): EvolutionCatalog
    {
        return (new EvolutionDefinitionMapper($this->weapons, $this->passiveIds))->map(ConfigJsonReader::load($file), $file);
    }

    public function decode(string $json, string $source = 'evolutions.json'): EvolutionCatalog
    {
        return (new EvolutionDefinitionMapper($this->weapons, $this->passiveIds))->map(ConfigJsonReader::decode($json, $source), $source);
    }
}
