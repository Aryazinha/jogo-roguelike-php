<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression;

use InvalidArgumentException;

final readonly class PassiveCatalog
{
    /** @var array<string, PassiveDefinition> */
    private array $definitions;

    /** @param iterable<PassiveDefinition> $definitions */
    public function __construct(iterable $definitions)
    {
        $indexed = [];
        foreach ($definitions as $definition) {
            $key = 'id:'.$definition->id;
            if (isset($indexed[$key])) {
                throw new InvalidArgumentException("ID de passivo duplicado: {$definition->id}.");
            }
            $indexed[$key] = $definition;
        }
        if ($indexed === []) {
            throw new InvalidArgumentException('O catálogo de passivos não pode estar vazio.');
        }
        $this->definitions = $indexed;
    }

    public function get(string $id): PassiveDefinition
    {
        return $this->definitions['id:'.$id] ?? throw new InvalidArgumentException("Passivo desconhecido: {$id}.");
    }

    public function has(string $id): bool
    {
        return isset($this->definitions['id:'.$id]);
    }

    /** @return list<PassiveDefinition> */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_map(static fn (PassiveDefinition $definition): string => $definition->id, $this->all());
    }
}
