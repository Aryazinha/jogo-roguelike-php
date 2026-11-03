<?php

declare(strict_types=1);

namespace Jogo\Domain\Character;

use InvalidArgumentException;

/** Catálogo imutável dos personagens disponíveis para seleção. */
final readonly class CharacterCatalog
{
    /** @var array<string, CharacterDefinition> */
    private array $definitions;

    /** @param iterable<CharacterDefinition> $definitions */
    public function __construct(iterable $definitions)
    {
        $indexed = [];
        $passives = [];
        foreach ($definitions as $definition) {
            $key = 'id:'.$definition->id;
            if (isset($indexed[$key])) {
                throw new InvalidArgumentException("ID de personagem duplicado: {$definition->id}.");
            }
            $passiveKey = 'id:'.$definition->passive->id;
            if (isset($passives[$passiveKey])) {
                throw new InvalidArgumentException("A passiva {$definition->passive->id} deve ser única por personagem.");
            }
            $indexed[$key] = $definition;
            $passives[$passiveKey] = true;
        }
        if ($indexed === []) {
            throw new InvalidArgumentException('O catálogo de personagens não pode estar vazio.');
        }
        $this->definitions = $indexed;
    }

    public function get(string $id): CharacterDefinition
    {
        return $this->definitions['id:'.$id] ?? throw new InvalidArgumentException("Personagem desconhecido: {$id}.");
    }

    public function has(string $id): bool
    {
        return isset($this->definitions['id:'.$id]);
    }

    /** @return list<CharacterDefinition> */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    public function count(): int
    {
        return count($this->definitions);
    }
}
