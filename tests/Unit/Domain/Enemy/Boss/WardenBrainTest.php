<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Enemy\Boss;

use Jogo\Domain\Enemy\Boss\BossAction;
use Jogo\Domain\Enemy\Boss\BossPhase;
use Jogo\Domain\Enemy\Boss\BossPhaseSettings;
use Jogo\Domain\Enemy\Boss\WardenBrain;
use Jogo\Domain\Value\Vector2;
use PHPUnit\Framework\TestCase;

/** Valores reais de enemies.json; passo fixo de 1/60 s, como o loop da A. */
final class WardenBrainTest extends TestCase
{
    private const DT = 1 / 60;

    public function test_starts_chasing_the_player(): void
    {
        $action = self::brain()->update(self::DT, 1.0, new Vector2(0.0, 50.0));

        self::assertTrue($action->direction->equals(new Vector2(0.0, 1.0)));
        self::assertSame(1.0, $action->speedMultiplier);
        self::assertSame(0, $action->summon);
    }

    public function test_full_charge_cycle_follows_the_configured_times(): void
    {
        $brain = self::brain();
        $timeline = self::simulate($brain, 20.0, static fn () => new Vector2(100.0, 0.0));

        // 8 s perseguindo, 0,8 s telegrafando, 1 s investindo, 1 s recuperando, de novo 8 s.
        self::assertSame([
            [0, BossPhase::CHASING], [480, BossPhase::TELEGRAPHING], [528, BossPhase::CHARGING],
            [588, BossPhase::RECOVERING], [648, BossPhase::CHASING], [1128, BossPhase::TELEGRAPHING],
            [1176, BossPhase::CHARGING],
        ], self::changes($timeline));
    }

    public function test_telegraph_stands_still_and_charge_keeps_the_locked_direction(): void
    {
        $brain = self::brain();
        $player = new Vector2(100.0, 0.0);
        self::simulate($brain, 8.0, static fn () => $player);
        self::assertSame(BossPhase::TELEGRAPHING, $brain->phase());
        self::assertTrue($brain->lockedDirection()->equals(new Vector2(1.0, 0.0)));

        // O jogador foge para baixo depois do aviso: a investida não corrige a mira.
        $actions = self::simulate($brain, 100 / 60, static fn () => new Vector2(0.0, 100.0));

        foreach ($actions as [$phase, $action]) {
            if ($phase === BossPhase::TELEGRAPHING) {
                self::assertSame(0.0, $action->speedMultiplier);
            }
            if ($phase === BossPhase::CHARGING) {
                self::assertTrue($action->direction->equals(new Vector2(1.0, 0.0)));
                self::assertSame(3.0, $action->speedMultiplier);
            }
        }
    }

    public function test_recovering_is_a_still_window(): void
    {
        $brain = self::brain();
        self::simulate($brain, 9.8, static fn () => new Vector2(100.0, 0.0));
        self::assertSame(BossPhase::RECOVERING, $brain->phase());

        $action = $brain->update(self::DT, 1.0, new Vector2(100.0, 0.0));

        self::assertSame(0.0, $action->speedMultiplier);
    }

    public function test_summons_once_when_crossing_each_threshold(): void
    {
        $brain = self::brain();
        $summons = [];
        // A vida desce de 100% a 30% em 7 s, sobe a 80% (cura não existe, mas o teste cobre) e desce de novo.
        $health = static fn (int $step): float => $step < 420 ? 1.0 - $step / 600 : ($step < 480 ? 0.8 : 0.35);
        for ($step = 0; $step < 600; $step++) {
            $action = $brain->update(self::DT, $health($step), new Vector2(100.0, 0.0));
            if ($action->summon > 0) {
                $summons[] = [$step, $action->summon];
            }
        }

        // 70% em 180 passos, 40% em 360. Voltar acima de 70% não invoca de novo.
        self::assertSame([[180, 8], [360, 8]], $summons);
    }

    public function test_summoning_stands_still_for_its_duration(): void
    {
        $brain = self::brain();
        $brain->update(self::DT, 0.69, new Vector2(100.0, 0.0));
        $steps = 0;
        while ($brain->phase() === BossPhase::SUMMONING) {
            self::assertSame(0.0, $brain->update(self::DT, 0.69, new Vector2(100.0, 0.0))->speedMultiplier);
            $steps++;
        }

        self::assertSame(60, $steps);
        self::assertSame(BossPhase::CHASING, $brain->phase());
    }

    public function test_one_big_hit_across_two_thresholds_summons_twice_in_a_row(): void
    {
        $brain = self::brain();
        $summons = [];
        for ($step = 0; $step < 200; $step++) {
            $action = $brain->update(self::DT, 0.3, new Vector2(100.0, 0.0));
            if ($action->summon > 0) {
                $summons[] = $step;
            }
        }

        self::assertSame([0, 60], $summons);
        self::assertFalse($brain->isEnraged(), '30% ainda não é fúria');
    }

    public function test_summon_waits_for_the_charge_to_finish(): void
    {
        $brain = self::brain();
        self::simulate($brain, 8.9, static fn () => new Vector2(100.0, 0.0));
        self::assertSame(BossPhase::CHARGING, $brain->phase());

        $first = null;
        for ($step = 0; $step < 200 && $first === null; $step++) {
            $phase = $brain->phase();
            if ($brain->update(self::DT, 0.5, new Vector2(100.0, 0.0))->summon > 0) {
                $first = $phase;
            }
        }

        self::assertSame(BossPhase::CHASING, $first);
    }

    public function test_enraged_is_faster_and_charges_more_often(): void
    {
        $brain = self::brain();

        $action = $brain->update(self::DT, 0.2, new Vector2(100.0, 0.0));
        while ($brain->phase() === BossPhase::SUMMONING || $action->summon > 0) {
            $action = $brain->update(self::DT, 0.2, new Vector2(100.0, 0.0));
        }
        self::assertTrue($brain->isEnraged());
        self::assertSame(1.3, $brain->update(self::DT, 0.2, new Vector2(100.0, 0.0))->speedMultiplier);

        $timeline = self::simulate($brain, 30.0, static fn () => new Vector2(100.0, 0.0), 0.2);
        $telegraphs = array_values(array_filter(self::changes($timeline), static fn ($c) => $c[1] === BossPhase::TELEGRAPHING));
        // Entre duas investidas: 0,8 + 1 + 1 de ação e 8 × 0,7 = 5,6 s de espera.
        self::assertSame((int) round((2.8 + 5.6) * 60), $telegraphs[1][0] - $telegraphs[0][0]);
        foreach ($timeline as [, $action]) {
            if ($action->speedMultiplier > 2.0) {
                self::assertEqualsWithDelta(3.9, $action->speedMultiplier, 1e-9, 'investida furiosa: 3 × 1,3');
            }
        }
    }

    public function test_pause_freezes_every_clock(): void
    {
        $brain = self::brain();
        self::simulate($brain, 7.9, static fn () => new Vector2(100.0, 0.0));

        for ($i = 0; $i < 1000; $i++) {
            self::assertSame(0.0, $brain->update(0.0, 0.1, new Vector2(100.0, 0.0))->speedMultiplier);
        }

        self::assertSame(BossPhase::CHASING, $brain->phase());
        self::assertFalse($brain->isEnraged());
    }

    public function test_same_input_gives_the_same_sequence(): void
    {
        $input = static fn (int $step) => new Vector2(cos($step / 30), sin($step / 50));
        $first = self::changes(self::simulate(self::brain(), 25.0, $input, 0.5));
        $second = self::changes(self::simulate(self::brain(), 25.0, $input, 0.5));

        self::assertSame($first, $second);
    }

    /**
     * @param  callable(int): Vector2  $toPlayer
     * @return list<array{BossPhase, BossAction}>
     */
    private static function simulate(WardenBrain $brain, float $seconds, callable $toPlayer, float $health = 1.0): array
    {
        $timeline = [];
        for ($step = 0; $step < (int) round($seconds * 60); $step++) {
            $phase = $brain->phase();
            $timeline[] = [$phase, $brain->update(self::DT, $health, $toPlayer($step))];
        }

        return $timeline;
    }

    /**
     * @param  list<array{BossPhase, BossAction}>  $timeline
     * @return list<array{int, BossPhase}> Passo em que cada fase começou.
     */
    private static function changes(array $timeline): array
    {
        $changes = [];
        foreach ($timeline as $step => [$phase]) {
            if ($changes === [] || end($changes)[1] !== $phase) {
                $changes[] = [$step, $phase];
            }
        }

        return $changes;
    }

    private static function brain(): WardenBrain
    {
        return new WardenBrain(new BossPhaseSettings(0.8, 1.0, 3.0, 1.0, 8.0, [0.7, 0.4], 8, 'zombie', 1.0, 0.25, 0.3, -0.3));
    }
}
