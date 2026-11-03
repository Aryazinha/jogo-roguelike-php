<?php

declare(strict_types=1);

namespace Jogo\Domain\Enemy;

use InvalidArgumentException;

/**
 * Catálogo imutável dos tipos de inimigo carregados para a partida.
 *
 * Além de indexar por ID, confere as referências entre tipos e os mínimos
 * do enunciado: 3 comuns (RF22-a), 2 especiais (RF36) e um boss (RF22-b).
 */
final readonly class EnemyCatalog
{
    public const MIN_COMMON = 3;

    public const MIN_SPECIAL = 2;

    /** @var array<string, EnemyArchetype> */
    private array $archetypes;

    /** @param iterable<EnemyArchetype> $archetypes */
    public function __construct(iterable $archetypes)
    {
        $indexed = [];
        foreach ($archetypes as $archetype) {
            $key = 'id:'.$archetype->id;
            if (isset($indexed[$key])) {
                throw new InvalidArgumentException("ID de inimigo duplicado: {$archetype->id}.");
            }
            $indexed[$key] = $archetype;
        }
        $this->archetypes = $indexed;

        $this->assertSplitReferences();
        $this->assertMinimums();
    }

    public function get(string $id): EnemyArchetype
    {
        return $this->archetypes['id:'.$id] ?? throw new InvalidArgumentException("Inimigo desconhecido: {$id}.");
    }

    public function has(string $id): bool
    {
        return isset($this->archetypes['id:'.$id]);
    }

    /** @return list<EnemyArchetype> */
    public function all(): array
    {
        return array_values($this->archetypes);
    }

    /** @return list<EnemyArchetype> */
    public function byCategory(EnemyCategory $category): array
    {
        return array_values(array_filter($this->archetypes, static fn (EnemyArchetype $a) => $a->category === $category));
    }

    /** @return list<EnemyArchetype> Tipos que podem ser sorteados no spawn para o nível informado. */
    public function spawnableAtLevel(int $level): array
    {
        return array_values(array_filter($this->archetypes, static fn (EnemyArchetype $a) => $a->canSpawnAtLevel($level)));
    }

    public function boss(): EnemyArchetype
    {
        return $this->byCategory(EnemyCategory::BOSS)[0];
    }

    private function assertSplitReferences(): void
    {
        foreach ($this->archetypes as $archetype) {
            if ($archetype->split === null) {
                continue;
            }
            $childId = $archetype->split->childTypeId;
            if (! $this->has($childId)) {
                throw new InvalidArgumentException("{$archetype->id} se divide em um inimigo inexistente: {$childId}.");
            }
            $child = $this->get($childId);
            // Um filho que também se divide geraria inimigos sem fim.
            if ($child->split !== null) {
                throw new InvalidArgumentException("{$childId} não pode se dividir, pois já é filho de {$archetype->id}.");
            }
            if (! $child->onlyFromSplit) {
                throw new InvalidArgumentException("{$childId} deve ser marcado como gerado apenas por divisão.");
            }
        }

        foreach ($this->archetypes as $archetype) {
            if ($archetype->onlyFromSplit && ! $this->isSomeoneChild($archetype->id)) {
                throw new InvalidArgumentException("{$archetype->id} é gerado só por divisão, mas nenhum inimigo o gera.");
            }
        }
    }

    private function isSomeoneChild(string $id): bool
    {
        foreach ($this->archetypes as $archetype) {
            if ($archetype->split?->childTypeId === $id) {
                return true;
            }
        }

        return false;
    }

    private function assertMinimums(): void
    {
        $commons = count($this->byCategory(EnemyCategory::COMMON));
        // Filhos de divisão não contam como inimigo especial próprio.
        $specials = count(array_filter($this->byCategory(EnemyCategory::SPECIAL), static fn (EnemyArchetype $a) => ! $a->onlyFromSplit));
        $bosses = count($this->byCategory(EnemyCategory::BOSS));

        if ($commons < self::MIN_COMMON) {
            throw new InvalidArgumentException('O RF22 exige pelo menos '.self::MIN_COMMON." inimigos comuns; há {$commons}.");
        }
        if ($specials < self::MIN_SPECIAL) {
            throw new InvalidArgumentException('O RF36 exige pelo menos '.self::MIN_SPECIAL." inimigos especiais; há {$specials}.");
        }
        if ($bosses !== 1) {
            throw new InvalidArgumentException("O jogo usa exatamente um boss (RF22); há {$bosses}.");
        }
    }
}
