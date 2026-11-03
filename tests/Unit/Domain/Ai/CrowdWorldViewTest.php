<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Ai;

use InvalidArgumentException;
use Jogo\Domain\Ai\Behavior\SeparationBehavior;
use Jogo\Domain\Ai\CrowdWorldView;
use Jogo\Domain\Ai\EntityView;
use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Domain\Value\Vector2;
use Jogo\Tests\Support\FixedWorldView;
use PHPUnit\Framework\TestCase;

final class CrowdWorldViewTest extends TestCase
{
    public function test_neighbors_include_the_boundary_and_come_sorted_by_id(): void
    {
        $crowd = CrowdWorldView::fromViews([
            FixedWorldView::enemy('enemy-2', 10.0, 0.0),
            FixedWorldView::enemy('enemy-10', 0.0, 40.0),
            FixedWorldView::enemy('enemy-1', 0.0, 0.0),
            FixedWorldView::enemy('enemy-3', 41.0, 0.0),
        ], Vector2::zero(), 40.0);

        $ids = array_map(static fn (EntityView $v) => $v->id, $crowd->neighbors(Vector2::zero(), 40.0));

        // Ordem textual, como na SpatialWorldView: "enemy-10" vem antes de "enemy-2".
        self::assertSame(['enemy-1', 'enemy-10', 'enemy-2'], $ids);
        self::assertSame([], $crowd->neighbors(new Vector2(500.0, 500.0), 40.0));
    }

    public function test_query_radius_larger_than_a_cell_still_finds_everyone(): void
    {
        $crowd = CrowdWorldView::fromViews([FixedWorldView::enemy('enemy-1', 0.0, 0.0),
            FixedWorldView::enemy('enemy-2', 150.0, 0.0)], Vector2::zero(), 40.0);

        self::assertCount(2, $crowd->neighbors(Vector2::zero(), 150.0));
    }

    public function test_find_returns_the_same_snapshot_object(): void
    {
        $view = FixedWorldView::enemy('enemy-1', 5.0, 5.0);
        $crowd = CrowdWorldView::fromViews([$view], new Vector2(1.0, 2.0), 40.0);

        self::assertSame($view, $crowd->find('enemy-1'));
        self::assertNull($crowd->find('enemy-9'));
        self::assertTrue($crowd->playerPosition()->equals(new Vector2(1.0, 2.0)));
    }

    public function test_duplicate_id_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CrowdWorldView::fromViews([FixedWorldView::enemy('enemy-1', 0.0, 0.0),
            FixedWorldView::enemy('enemy-1', 9.0, 0.0)], Vector2::zero(), 40.0);
    }

    public function test_fast_separation_matches_the_generic_path(): void
    {
        // 80 inimigos sorteados, alguns empilhados: os dois caminhos precisam dar o mesmo vetor.
        $random = new SeededRandomIndex(11);
        $views = [];
        for ($i = 0; $i < 80; $i++) {
            $views[] = FixedWorldView::enemy("enemy-{$i}", (float) $random->nextIndex(120), (float) $random->nextIndex(120));
        }
        $views[] = FixedWorldView::enemy('enemy-stacked', $views[0]->position->x(), $views[0]->position->y());
        $views[] = new EntityView('player-1', new Vector2(60.0, 60.0), 16.0, 'player', true);
        $views[] = new EntityView('enemy-dead', new Vector2(61.0, 60.0), 14.0, 'enemy', false);
        $crowd = CrowdWorldView::fromViews($views, new Vector2(60.0, 60.0), 40.0);
        $generic = new FixedWorldView(new Vector2(60.0, 60.0), $views);
        $behavior = new SeparationBehavior(40.0);

        foreach ($views as $view) {
            $fast = $behavior->direction($view, $crowd, 1 / 60);
            $slow = $behavior->direction($view, $generic, 1 / 60);
            self::assertTrue($fast->equals($slow, 1e-9), $view->id);
        }
    }
}
