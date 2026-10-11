<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon\Upgrade;

use InvalidArgumentException;

final readonly class UpgradeCatalog
{
    /** @var array<string, UpgradeDefinition> */
    private array $definitions;

    /** @param iterable<UpgradeDefinition> $definitions */
    public function __construct(iterable $definitions)
    {
        $indexed = [];
        foreach ($definitions as $definition) {
            $key = $definition->weaponId.':'.$definition->level;
            if (isset($indexed[$key])) {
                throw new InvalidArgumentException("Upgrade duplicado: {$key}.");
            }
            $indexed[$key] = $definition;
        }
        if ($indexed === []) {
            throw new InvalidArgumentException('O catálogo de upgrades não pode estar vazio.');
        }
        $this->definitions = $indexed;
    }

    public function get(string $weaponId, int $level): UpgradeDefinition
    {
        return $this->definitions[$weaponId.':'.$level] ?? throw new InvalidArgumentException("Upgrade desconhecido: {$weaponId}:{$level}.");
    }

    /** @return list<UpgradeDefinition> */
    public function all(): array
    {
        return array_values($this->definitions);
    }
}
