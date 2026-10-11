<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon\Targeting;

use Jogo\Domain\Ai\WorldView;

final class NearestEnemyTargeting implements TargetingStrategy
{
    public function select(TargetingContext $context, WorldView $world): ?TargetingResult
    {
        $nearest = null;
        $distance = INF;
        foreach (TargetCandidates::enemies($context, $world) as $candidate) {
            $next = $context->origin->distanceTo($candidate->position);
            if ($next < $distance) {
                $nearest = $candidate;
                $distance = $next;
            }
        }

        return $nearest === null ? null : TargetCandidates::result($context, $nearest);
    }
}
