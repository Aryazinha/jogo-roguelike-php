<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Scenery;

use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Domain\Scenery\Breakable;
use Jogo\Domain\Scenery\BreakableType;
use Jogo\Domain\Scenery\DropChance;
use Jogo\Domain\Scenery\SceneryPlacer;
use Jogo\Domain\Scenery\ScenerySettings;
use Jogo\Domain\Scenery\TemporaryBonus;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\ModifierType;
use Jogo\Domain\Value\StatModifier;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\MapLayout;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

final class SceneryPlacerTest extends TestCase
{
    public function test_places_the_density_without_overlap_and_away_from_the_start(): void
    {
        $world = self::world();

        $placed = (new SceneryPlacer(self::settings(8.0), new SeededRandomIndex(3)))->place($world);

        // Área útil 2000 × 2000 = 4 blocos de 1000 × 1000 px, 8 por bloco.
        self::assertSame(32, $placed);
        $breakables = $world->breakables();
        foreach ($breakables as $index => $breakable) {
            self::assertTrue($world->layout()->contains($breakable->position()));
            self::assertGreaterThanOrEqual(150.0 + $breakable->collisionRadius(),
                $breakable->position()->distanceTo($world->player()->position()));
            foreach (array_slice($breakables, $index + 1) as $other) {
                self::assertGreaterThanOrEqual($breakable->collisionRadius() + $other->collisionRadius(),
                    $breakable->position()->distanceTo($other->position()));
            }
        }
    }

    public function test_same_seed_places_the_same_scenery(): void
    {
        $layout = static function (int $seed): array {
            $world = self::world();
            (new SceneryPlacer(self::settings(8.0), new SeededRandomIndex($seed)))->place($world);

            return array_map(static fn (Breakable $b) => [$b->id(), $b->position()->x(), $b->position()->y()], $world->breakables());
        };

        self::assertSame($layout(11), $layout(11));
        self::assertNotSame($layout(11), $layout(12));
    }

    public function test_zero_density_places_nothing(): void
    {
        $world = self::world();

        self::assertSame(0, (new SceneryPlacer(self::settings(0.0), new SeededRandomIndex(1)))->place($world));
        self::assertSame([], $world->breakables());
    }

    private static function world(): GameWorld
    {
        return new GameWorld(new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(20.0)),
            new MapLayout(2040.0, 2040.0, 20.0, 300.0, 400.0));
    }

    private static function settings(float $density): ScenerySettings
    {
        return new ScenerySettings($density, [
            new BreakableType('grass', 'Grama', 1.0, 12.0, false, [new DropChance('nenhum', 1.0)]),
            new BreakableType('crate', 'Caixote', 3.0, 18.0, true, [new DropChance('steak', 1.0)]),
        ], [new TemporaryBonus('furia', 'Furia', 15.0, [new StatModifier('dano', ModifierType::PERCENT, 0.5, 'teste')])]);
    }
}
