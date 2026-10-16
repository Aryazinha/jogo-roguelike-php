<?php

declare(strict_types=1);

namespace Jogo\Domain\Wave;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;

/**
 * A curva de ondas de waves.json (RF05, RF28, RF29).
 *
 * As faixas cobrem o tempo inteiro, sem buracos: a primeira começa em 0, cada
 * uma começa onde a anterior termina e só a última é aberta (até o fim).
 */
final readonly class WaveSettings
{
    /** @var list<WaveBand> */
    public array $bands;

    /** @param list<WaveBand> $bands */
    public function __construct(
        array $bands,
        public int $maxAlive,
        public float $healthPerLevel,
        public float $damagePerLevel,
    ) {
        if ($bands === []) {
            throw new InvalidArgumentException('É preciso pelo menos uma faixa de ondas.');
        }
        if ($maxAlive < 1) {
            throw new InvalidArgumentException('maxAlive deve ser maior ou igual a um.');
        }
        CombatValidation::nonNegative($healthPerLevel, 'healthPerLevel');
        CombatValidation::nonNegative($damagePerLevel, 'damagePerLevel');

        $expectedStart = 0.0;
        foreach ($bands as $index => $band) {
            if ($band->fromSeconds !== $expectedStart) {
                throw new InvalidArgumentException("faixa {$index} deveria começar em {$expectedStart} s.");
            }
            $last = $index === count($bands) - 1;
            if ($last !== ($band->toSeconds === null)) {
                throw new InvalidArgumentException('só a última faixa pode ficar sem fim, e ela precisa ficar.');
            }
            $expectedStart = $band->toSeconds ?? INF;
        }
        $this->bands = $bands;
    }

    public function bandAt(float $elapsedSeconds): WaveBand
    {
        foreach ($this->bands as $band) {
            if ($band->contains($elapsedSeconds)) {
                return $band;
            }
        }

        // As faixas cobrem de 0 ao infinito; só um tempo negativo chegaria aqui.
        throw new InvalidArgumentException('tempo fora das faixas de ondas.');
    }

    public function bandIndexAt(float $elapsedSeconds): int
    {
        return (int) array_search($this->bandAt($elapsedSeconds), $this->bands, true);
    }

    /** Nível 1 é o valor base; cada nível a mais soma healthPerLevel (0,04 = +4%). */
    public function healthMultiplier(int $level): float
    {
        return 1.0 + $this->healthPerLevel * max(0, $level - 1);
    }

    public function damageMultiplier(int $level): float
    {
        return 1.0 + $this->damagePerLevel * max(0, $level - 1);
    }
}
