<?php

declare(strict_types=1);

namespace Jogo\Tests\Support;

use Jogo\Domain\Ai\EntityView;
use Jogo\Domain\Ai\WorldView;
use Jogo\Domain\Value\Vector2;

/** WorldView montada à mão para testar comportamentos sem grade espacial. */
final class FixedWorldView implements WorldView
{
    /** @var list<array{Vector2, float}> */
    public array $queries = [];

    /** @param list<EntityView> $entities */
    public function __construct(private readonly Vector2 $player, private readonly array $entities = []) {}

    public function playerPosition(): Vector2
    {
        return $this->player;
    }

    public function neighbors(Vector2 $center, float $radius): array
    {
        $this->queries[] = [$center, $radius];
        $found = array_filter($this->entities, static fn (EntityView $e) => $e->position->distanceTo($center) <= $radius);
        usort($found, static fn (EntityView $a, EntityView $b) => strcmp($a->id, $b->id));

        return $found;
    }

    public static function enemy(string $id, float $x, float $y): EntityView
    {
        return new EntityView($id, new Vector2($x, $y), 14.0, 'enemy', true);
    }
}
