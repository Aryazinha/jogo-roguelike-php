<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression\Reward;

use InvalidArgumentException;

final readonly class TemporaryBonusCatalog
{
    /** @var array<string, TemporaryBonusDefinition> */
    private array $definitions;

    /** @param iterable<TemporaryBonusDefinition> $definitions */
    public function __construct(iterable $definitions)
    {
        $indexed = [];
        foreach ($definitions as $definition) {
            $key = 'id:'.$definition->id;
            if (isset($indexed[$key])) {
                throw new InvalidArgumentException("Bônus duplicado: {$definition->id}.");
            }
            $indexed[$key] = $definition;
        }
        if ($indexed === []) {
            throw new InvalidArgumentException('O catálogo de bônus não pode estar vazio.');
        }
        $this->definitions = $indexed;
    }

    public function get(string $id): TemporaryBonusDefinition
    {
        return $this->definitions['id:'.$id] ?? throw new InvalidArgumentException("Bônus desconhecido: {$id}.");
    }

    public function has(string $id): bool
    {
        return isset($this->definitions['id:'.$id]);
    }
}
