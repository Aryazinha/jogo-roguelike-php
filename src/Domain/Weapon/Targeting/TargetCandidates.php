<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon\Targeting;

use Jogo\Domain\Ai\EntityView;
use Jogo\Domain\Ai\WorldView;
use Jogo\Domain\System\CollisionSystem;
use Jogo\Domain\Value\Vector2;

/** @internal Filtragem defensiva compartilhada pelas miras individuais. */
final class TargetCandidates
{
    /** @return list<EntityView> */
    public static function enemies(TargetingContext $context, WorldView $world): array
    {
        $candidates = [];
        foreach ($world->neighbors($context->origin, $context->range) as $view) {
            if ($view->kind === 'enemy' && $view->isAlive === true
                && CollisionSystem::circlesIntersect($context->origin, $context->range, $view->position, 0.0)) {
                $candidates['id:'.$view->id] = $view;
            }
        }
        ksort($candidates, SORT_STRING);

        return array_values($candidates);
    }

    public static function result(TargetingContext $context, EntityView $view): TargetingResult
    {
        $offset = $view->position->subtract($context->origin);
        // Divisão componente a componente evita perder direções de deslocamentos muito pequenos.
        $length = $offset->length();
        $direction = $length === 0.0 ? $context->facing
            : new Vector2($offset->x() / $length, $offset->y() / $length);

        return new TargetingResult($direction, $view->position, $view->id);
    }
}
