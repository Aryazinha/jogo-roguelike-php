<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon\Targeting;

use Jogo\Domain\Ai\WorldView;
use Jogo\Domain\System\GeometryMetrics;

final class NearestEnemyTargeting implements TargetingStrategy
{
    public function __construct(private readonly ?GeometryMetrics $metrics = null) {}

    public function select(TargetingContext $context, WorldView $world): ?TargetingResult
    {
        $nearest = null;
        $distance = INF;
        foreach (TargetCandidates::enemies($context, $world, $this->metrics) as $candidate) {
            $next = $context->origin->distanceTo($candidate->position);
            if ($next < $distance) {
                $nearest = $candidate;
                $distance = $next;
            }
        }

        return $nearest === null ? null : TargetCandidates::result($context, $nearest);
    }
}
