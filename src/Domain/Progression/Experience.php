<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression;

use InvalidArgumentException;

/** XP acumulado e nível do jogador durante a partida (RF12, RF13). */
final class Experience
{
    public const STARTING_LEVEL = 1;

    private int $level = self::STARTING_LEVEL;

    private int $currentXp = 0;

    private int $totalXp = 0;

    public function level(): int
    {
        return $this->level;
    }

    /** XP acumulado dentro do nível atual. */
    public function currentXp(): int
    {
        return $this->currentXp;
    }

    public function totalXp(): int
    {
        return $this->totalXp;
    }

    public function requiredForNextLevel(): int
    {
        return LevelFormula::requiredXp($this->level);
    }

    /**
     * Soma XP e sobe quantos níveis forem alcançados, guardando o excedente.
     *
     * @return list<int> Níveis alcançados neste ganho, em ordem.
     */
    public function gain(int $amount): array
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('O ganho de XP não pode ser negativo.');
        }
        $this->totalXp += $amount;
        $this->currentXp += $amount;
        $reached = [];
        while ($this->currentXp >= $this->requiredForNextLevel()) {
            $this->currentXp -= $this->requiredForNextLevel();
            $this->level++;
            $reached[] = $this->level;
        }

        return $reached;
    }
}
