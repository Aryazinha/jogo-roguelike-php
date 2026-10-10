<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon;

use InvalidArgumentException;

/** Catálogo imutável, sem metadados de arquivo ou estado de execução. */
final readonly class WeaponCatalog
{
    /** @var array<string, WeaponDefinition> */
    private array $definitions;

    /** @param iterable<WeaponDefinition> $definitions */
    public function __construct(iterable $definitions)
    {
        $indexed = [];
        foreach ($definitions as $definition) {
            $key = 'id:'.$definition->id;
            if (isset($indexed[$key])) {
                throw new InvalidArgumentException("ID de arma duplicado: {$definition->id}.");
            }
            $indexed[$key] = $definition;
        }
        if ($indexed === []) {
            throw new InvalidArgumentException('O catálogo de armas não pode estar vazio.');
        }
        $this->definitions = $indexed;
    }

    public function get(string $id): WeaponDefinition
    {
        return $this->definitions['id:'.$id] ?? throw new InvalidArgumentException("Arma desconhecida: {$id}.");
    }

    public function has(string $id): bool
    {
        return isset($this->definitions['id:'.$id]);
    }

    /** @return list<WeaponDefinition> */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    public function count(): int
    {
        return count($this->definitions);
    }
}
