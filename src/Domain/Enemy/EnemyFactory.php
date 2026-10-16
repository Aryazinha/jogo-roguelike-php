<?php

declare(strict_types=1);

namespace Jogo\Domain\Enemy;

use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Vector2;

/**
 * Cria inimigos a partir do catálogo, com IDs sequenciais e reproduzíveis.
 *
 * Como o contador começa do zero a cada partida, a mesma sequência de spawns
 * gera os mesmos IDs, o que facilita comparar simulações com a mesma seed.
 */
final class EnemyFactory
{
    private int $created = 0;

    public function __construct(private readonly EnemyCatalog $catalog, private readonly string $idPrefix = 'enemy')
    {
        CombatValidation::requiredId($idPrefix, 'idPrefix');
    }

    public function create(string $typeId, Vector2 $position, float $healthMultiplier = 1.0, float $damageMultiplier = 1.0): Enemy
    {
        $archetype = $this->catalog->get($typeId);
        // Valida antes de gastar o número da sequência.
        $enemy = new Enemy("{$this->idPrefix}-".($this->created + 1), $archetype, $position, $healthMultiplier, $damageMultiplier);
        $this->created++;

        return $enemy;
    }

    public function createdCount(): int
    {
        return $this->created;
    }
}
