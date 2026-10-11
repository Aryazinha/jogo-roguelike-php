<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon\Upgrade;

use InvalidArgumentException;

final readonly class EvolutionCatalog
{
    /** @var array<string, EvolutionDefinition> */
    private array $definitions;

    /** @param iterable<EvolutionDefinition> $definitions */
    public function __construct(iterable $definitions)
    {
        $indexed = [];
        foreach ($definitions as $definition) {
            $key = 'id:'.$definition->definition->id;
            if (isset($indexed[$key])) {
                throw new InvalidArgumentException("Evolução duplicada: {$definition->definition->id}.");
            }
            $indexed[$key] = $definition;
        }
        if ($indexed === []) {
            throw new InvalidArgumentException('O catálogo de evoluções não pode estar vazio.');
        }
        $this->definitions = $indexed;
    }

    public function get(string $id): EvolutionDefinition
    {
        return $this->definitions['id:'.$id] ?? throw new InvalidArgumentException("Evolução desconhecida: {$id}.");
    }

    /** @return list<EvolutionDefinition> */
    public function all(): array
    {
        return array_values($this->definitions);
    }
}
