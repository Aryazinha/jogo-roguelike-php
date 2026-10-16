<?php

declare(strict_types=1);

namespace Jogo\Domain\Wave;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;

/** Uma faixa de tempo de waves.json: de quanto em quanto tempo e quantos inimigos surgem. */
final readonly class WaveBand
{
    /**
     * @param  ?float  $toSeconds  null = até o fim da partida.
     * @param  list<string>  $typeIds  Tipos que podem ser sorteados nesta faixa.
     */
    public function __construct(
        public float $fromSeconds,
        public ?float $toSeconds,
        public float $intervalSeconds,
        public int $perSpawn,
        public array $typeIds,
    ) {
        CombatValidation::nonNegative($fromSeconds, 'fromSeconds');
        CombatValidation::positive($intervalSeconds, 'intervalSeconds');
        if ($toSeconds !== null && (! is_finite($toSeconds) || $toSeconds <= $fromSeconds)) {
            throw new InvalidArgumentException('toSeconds deve ser maior que fromSeconds.');
        }
        if ($perSpawn < 1) {
            throw new InvalidArgumentException('perSpawn deve ser maior ou igual a um.');
        }
        if ($typeIds === [] || count(array_unique($typeIds)) !== count($typeIds)) {
            throw new InvalidArgumentException('typeIds deve ter ao menos um tipo e não repetir.');
        }
    }

    /** O início conta, o fim não: em 120 s vale a faixa que começa em 120. */
    public function contains(float $elapsedSeconds): bool
    {
        return $elapsedSeconds >= $this->fromSeconds && ($this->toSeconds === null || $elapsedSeconds < $this->toSeconds);
    }
}
