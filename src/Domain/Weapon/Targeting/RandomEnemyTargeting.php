<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon\Targeting;

use Jogo\Domain\Ai\WorldView;
use Jogo\Domain\Random\RandomIndex;
use UnexpectedValueException;

final class RandomEnemyTargeting implements TargetingStrategy
{
    public function __construct(private readonly RandomIndex $random) {}

    public function select(TargetingContext $context, WorldView $world): ?TargetingResult
    {
        $candidates = TargetCandidates::enemies($context, $world);
        if ($candidates === []) {
            return null;
        }
        $index = $this->random->nextIndex(count($candidates));
        if (! isset($candidates[$index])) {
            throw new UnexpectedValueException('O gerador devolveu um índice fora dos candidatos.');
        }

        return TargetCandidates::result($context, $candidates[$index]);
    }
}
