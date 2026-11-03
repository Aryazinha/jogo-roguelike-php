<?php

declare(strict_types=1);

namespace Jogo\Domain\Enemy;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;

/** O que um inimigo deixa ao morrer (RF36: Slime gera inimigos menores). */
final readonly class SplitRule
{
    public function __construct(public string $childTypeId, public int $count)
    {
        CombatValidation::requiredId($childTypeId, 'childTypeId');
        if ($count < 1) {
            throw new InvalidArgumentException('count da divisão deve ser maior ou igual a um.');
        }
    }
}
