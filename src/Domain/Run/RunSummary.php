<?php

declare(strict_types=1);

namespace Jogo\Domain\Run;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;

/** Resumo imutável de uma partida encerrada (RF09, RF30); entrada da pontuação. */
final readonly class RunSummary
{
    public function __construct(
        public string $characterId,
        public int $level,
        public int $enemiesKilled,
        public float $survivalSeconds,
        public bool $won,
        public int $seed,
    ) {
        CombatValidation::requiredId($characterId, 'characterId');
        if ($level < 1 || $enemiesKilled < 0) {
            throw new InvalidArgumentException('O resumo exige nível >= 1 e mortes >= 0.');
        }
        CombatValidation::nonNegative($survivalSeconds, 'survivalSeconds');
    }

    /** Segundos inteiros sobrevividos, como gravado no ranking. */
    public function wholeSeconds(): int
    {
        return (int) floor($this->survivalSeconds);
    }
}
