<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;

/** XP por tipo de inimigo, a partir dos metadados de conteúdo da Frente C. */
final readonly class EnemyXpTable
{
    /** @var array<string, XpRange> */
    private array $ranges;

    /** @param array<string, XpRange> $ranges Indexado pelo enemyTypeId. */
    public function __construct(array $ranges)
    {
        if ($ranges === []) {
            throw new InvalidArgumentException('A tabela de XP não pode estar vazia.');
        }
        $indexed = [];
        foreach ($ranges as $typeId => $range) {
            CombatValidation::requiredId((string) $typeId, 'enemyTypeId');
            $indexed['id:'.$typeId] = $range;
        }
        $this->ranges = $indexed;
    }

    public function has(string $enemyTypeId): bool
    {
        return isset($this->ranges['id:'.$enemyTypeId]);
    }

    public function get(string $enemyTypeId): XpRange
    {
        return $this->ranges['id:'.$enemyTypeId] ?? throw new InvalidArgumentException("Tipo de inimigo sem XP: {$enemyTypeId}.");
    }
}
