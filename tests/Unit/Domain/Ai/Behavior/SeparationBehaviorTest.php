<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Ai\Behavior;

use InvalidArgumentException;
use Jogo\Domain\Ai\Behavior\SeparationBehavior;
use Jogo\Domain\Ai\EntityView;
use Jogo\Domain\Value\Vector2;
use Jogo\Tests\Support\FixedWorldView;
use PHPUnit\Framework\TestCase;

final class SeparationBehaviorTest extends TestCase
{
    public function test_pushes_away_from_a_close_neighbor(): void
    {
        $self = FixedWorldView::enemy('enemy-1', 0.0, 0.0);
        $world = new FixedWorldView(new Vector2(500.0, 0.0), [$self, FixedWorldView::enemy('enemy-2', 10.0, 0.0)]);

        $direction = (new SeparationBehavior(40.0))->direction($self, $world, 1 / 60);

        // Força 1 - 10/40 = 0,75 para a esquerda.
        self::assertTrue($direction->equals(new Vector2(-0.75, 0.0)));
    }

    public function test_push_is_capped_at_unit_length(): void
    {
        $self = FixedWorldView::enemy('enemy-1', 0.0, 0.0);
        $world = new FixedWorldView(Vector2::zero(), [$self, FixedWorldView::enemy('enemy-2', 2.0, 0.0),
            FixedWorldView::enemy('enemy-3', 2.0, 1.0)]);

        $direction = (new SeparationBehavior(40.0))->direction($self, $world, 1 / 60);

        self::assertEqualsWithDelta(1.0, $direction->length(), 1e-9);
    }

    public function test_enemy_surrounded_on_both_sides_barely_moves(): void
    {
        $self = FixedWorldView::enemy('enemy-1', 0.0, 0.0);
        $world = new FixedWorldView(Vector2::zero(), [$self, FixedWorldView::enemy('enemy-2', 10.0, 0.0),
            FixedWorldView::enemy('enemy-3', -10.5, 0.0)]);

        $direction = (new SeparationBehavior(40.0))->direction($self, $world, 1 / 60);

        self::assertLessThan(0.05, $direction->length());
    }

    public function test_closer_neighbor_pushes_harder(): void
    {
        // Vizinho a 5 px pela direita e outro a 20 px por baixo: a fuga pende para a esquerda.
        $self = FixedWorldView::enemy('enemy-1', 0.0, 0.0);
        $world = new FixedWorldView(Vector2::zero(), [$self, FixedWorldView::enemy('enemy-2', 5.0, 0.0),
            FixedWorldView::enemy('enemy-3', 0.0, 20.0)]);

        $direction = (new SeparationBehavior(36.0))->direction($self, $world, 1 / 60);

        // Forças 1 - 5/36 para a esquerda e 1 - 20/36 para cima.
        self::assertTrue($direction->equals(new Vector2(-(1 - 5 / 36), -(1 - 20 / 36))));
        self::assertGreaterThan(abs($direction->y()), abs($direction->x()));
    }

    public function test_ignores_itself_the_player_others_kinds_dead_and_far_entities(): void
    {
        $self = FixedWorldView::enemy('enemy-1', 0.0, 0.0);
        $world = new FixedWorldView(Vector2::zero(), [
            $self,
            new EntityView('player-1', new Vector2(5.0, 0.0), 16.0, 'player', true),
            new EntityView('crate-1', new Vector2(0.0, 5.0), 18.0, 'breakable', true),
            new EntityView('enemy-2', new Vector2(-5.0, 0.0), 14.0, 'enemy', false),
            FixedWorldView::enemy('enemy-3', 100.0, 0.0),
        ]);

        $behavior = new SeparationBehavior(36.0);

        self::assertTrue($behavior->direction($self, $world, 1 / 60)->equals(Vector2::zero()));
        self::assertSame(36.0, $world->queries[0][1]);
    }

    public function test_stacked_enemies_split_in_opposite_directions(): void
    {
        $a = FixedWorldView::enemy('enemy-1', 50.0, 50.0);
        $b = FixedWorldView::enemy('enemy-2', 50.0, 50.0);
        $world = new FixedWorldView(Vector2::zero(), [$a, $b]);
        $behavior = new SeparationBehavior(36.0);

        $first = $behavior->direction($a, $world, 1 / 60);
        $second = $behavior->direction($b, $world, 1 / 60);

        self::assertEqualsWithDelta(1.0, $first->length(), 1e-9);
        self::assertTrue($first->add($second)->equals(Vector2::zero()));
        self::assertTrue($behavior->direction($a, $world, 1 / 60)->equals($first), 'precisa ser determinístico');
    }

    public function test_radius_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SeparationBehavior(0.0);
    }
}
