<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Ai\Behavior;

use Jogo\Domain\Ai\Behavior\KeepDistanceBehavior;
use Jogo\Domain\Ai\Behavior\RangedMode;
use Jogo\Domain\Enemy\RangedSettings;
use Jogo\Domain\Value\Vector2;
use Jogo\Tests\Support\FixedWorldView;
use PHPUnit\Framework\TestCase;

/** Distância ideal 200 e margem 40: orbita entre 160 e 240. */
final class KeepDistanceBehaviorTest extends TestCase
{
    public function test_far_away_it_chases(): void
    {
        $behavior = self::behavior();

        $direction = self::step($behavior, 300.0);

        self::assertTrue($direction->equals(new Vector2(-1.0, 0.0)));
        self::assertSame(RangedMode::CHASE, $behavior->mode('enemy-1'));
    }

    public function test_too_close_it_flees(): void
    {
        $behavior = self::behavior();

        $direction = self::step($behavior, 100.0);

        self::assertTrue($direction->equals(new Vector2(1.0, 0.0)));
        self::assertSame(RangedMode::FLEE, $behavior->mode('enemy-1'));
    }

    public function test_in_range_it_walks_sideways(): void
    {
        $behavior = self::behavior();

        $direction = self::step($behavior, 200.0);

        self::assertSame(RangedMode::ORBIT, $behavior->mode('enemy-1'));
        self::assertEqualsWithDelta(0.0, $direction->x(), 1e-9);
        self::assertEqualsWithDelta(1.0, abs($direction->y()), 1e-9);
    }

    public function test_chasing_only_stops_at_the_ideal_distance(): void
    {
        $behavior = self::behavior();

        $modes = [];
        foreach ([300.0, 230.0, 200.0] as $distance) {
            self::step($behavior, $distance);
            $modes[] = $behavior->mode('enemy-1');
        }

        // A 230 px está dentro da margem, mas ainda não chegou na ideal.
        self::assertSame([RangedMode::CHASE, RangedMode::CHASE, RangedMode::ORBIT], $modes);
    }

    public function test_orbit_survives_small_changes_on_both_sides(): void
    {
        $behavior = self::behavior();
        self::step($behavior, 200.0);

        // Oscilar dentro da faixa 160-240 não muda o modo: é para isso que serve a histerese.
        foreach ([239.0, 161.0, 240.0, 160.0, 210.0] as $distance) {
            self::step($behavior, $distance);
            self::assertSame(RangedMode::ORBIT, $behavior->mode('enemy-1'), (string) $distance);
        }
        self::step($behavior, 240.1);
        self::assertSame(RangedMode::CHASE, $behavior->mode('enemy-1'));
    }

    public function test_fleeing_only_stops_back_at_the_ideal_distance(): void
    {
        $behavior = self::behavior();
        $modes = [];
        foreach ([200.0, 150.0, 180.0, 200.0] as $distance) {
            self::step($behavior, $distance);
            $modes[] = $behavior->mode('enemy-1');
        }

        // A 180 px já voltou para a faixa, mas continua fugindo até a distância ideal.
        self::assertSame([RangedMode::ORBIT, RangedMode::FLEE, RangedMode::FLEE, RangedMode::ORBIT], $modes);
    }

    public function test_each_enemy_has_its_own_mode_and_can_be_forgotten(): void
    {
        $behavior = self::behavior();
        self::step($behavior, 300.0, 'enemy-1');
        self::step($behavior, 100.0, 'enemy-2');

        self::assertSame(RangedMode::CHASE, $behavior->mode('enemy-1'));
        self::assertSame(RangedMode::FLEE, $behavior->mode('enemy-2'));
        $behavior->forget('enemy-1');
        self::assertNull($behavior->mode('enemy-1'));
    }

    public function test_orbit_side_is_fixed_per_enemy(): void
    {
        $behavior = self::behavior();
        $sides = [];
        foreach (['enemy-1', 'enemy-2', 'enemy-3', 'enemy-4', 'enemy-5', 'enemy-6'] as $id) {
            $first = self::step($behavior, 200.0, $id);
            self::assertTrue(self::step($behavior, 200.0, $id)->equals($first));
            $sides[$first->y() > 0 ? 'baixo' : 'cima'] = true;
        }

        self::assertCount(2, $sides, 'alguns giram para cada lado');
    }

    private static function behavior(): KeepDistanceBehavior
    {
        return new KeepDistanceBehavior(new RangedSettings(200.0, 40.0, 2.2));
    }

    /** Inimigo à direita do jogador, na distância pedida. */
    private static function step(KeepDistanceBehavior $behavior, float $distance, string $id = 'enemy-1'): Vector2
    {
        return $behavior->direction(FixedWorldView::enemy($id, $distance, 0.0), new FixedWorldView(Vector2::zero()), 1 / 60);
    }
}
