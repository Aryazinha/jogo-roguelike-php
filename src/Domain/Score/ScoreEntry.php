<?php

declare(strict_types=1);

namespace Jogo\Domain\Score;

use InvalidArgumentException;
use Jogo\Domain\Run\RunSummary;
use Jogo\Domain\Value\CombatValidation;

/** Resultado associado ao nome do jogador (RF33), pronto para o ranking. */
final readonly class ScoreEntry
{
    public function __construct(
        public string $playerName,
        public string $characterId,
        public int $score,
        public int $level,
        public int $enemiesKilled,
        public int $survivalSeconds,
        public bool $won,
        public int $seed,
    ) {
        CombatValidation::requiredId($playerName, 'playerName');
        CombatValidation::requiredId($characterId, 'characterId');
        if ($score < 0 || $level < 1 || $enemiesKilled < 0 || $survivalSeconds < 0) {
            throw new InvalidArgumentException('Pontuação, nível, mortes e tempo precisam ser válidos.');
        }
    }

    public static function fromSummary(RunSummary $summary, PlayerName $name, ScoreCalculator $calculator): self
    {
        return new self($name->value, $summary->characterId, $calculator->calculate($summary), $summary->level,
            $summary->enemiesKilled, $summary->wholeSeconds(), $summary->won, $summary->seed);
    }
}
