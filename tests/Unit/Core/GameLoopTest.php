<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Core;

use InvalidArgumentException;
use Jogo\Core\GameLoop;
use Jogo\Domain\Value\Cooldown;
use Jogo\Domain\Value\Health;
use Jogo\Tests\Support\ManualClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class GameLoopTest extends TestCase
{
    public function test_it_updates_with_a_fixed_step_and_renders_after_updates(): void
    {
        $clock = new ManualClock;
        $calls = [];
        $loop = new GameLoop(
            $clock,
            static function (float $dt) use (&$calls): void {
                $calls[] = ['update', $dt];
            },
            static function (float $alpha) use (&$calls): void {
                $calls[] = ['render', $alpha];
            },
        );

        $clock->advance(GameLoop::STEP_SECONDS * 2.5);

        self::assertSame(2, $loop->tick());
        self::assertCount(3, $calls);
        self::assertSame(['update', 1.0 / 60.0], $calls[0]);
        self::assertSame(['update', 1.0 / 60.0], $calls[1]);
        self::assertSame('render', $calls[2][0]);
        self::assertEqualsWithDelta(0.5, $calls[2][1], 1.0E-9);
        self::assertSame(2.0 / 60.0, $loop->elapsedSeconds());
    }

    public function test_it_accumulates_partial_steps_and_renders_without_an_update(): void
    {
        $clock = new ManualClock;
        $updates = 0;
        $alphas = [];
        $loop = new GameLoop(
            $clock,
            static function (float $dt) use (&$updates): void {
                $updates++;
            },
            static function (float $alpha) use (&$alphas): void {
                $alphas[] = $alpha;
            },
        );

        self::assertSame(0, $loop->tick());
        $clock->advance(GameLoop::STEP_SECONDS / 2);
        self::assertSame(0, $loop->tick());
        self::assertSame(0, $updates);
        self::assertEqualsWithDelta(0.5, $alphas[1], 1.0E-9);
        $clock->advance(GameLoop::STEP_SECONDS / 2);
        self::assertSame(1, $loop->tick());
        self::assertSame(1, $updates);
        self::assertCount(3, $alphas);
        self::assertEqualsWithDelta(0.0, $alphas[2], 1.0E-9);
    }

    public function test_it_limits_backlog_and_does_not_catch_up_on_the_next_frame(): void
    {
        $clock = new ManualClock;
        $alphas = [];
        $loop = new GameLoop(
            $clock,
            static function (float $dt): void {},
            static function (float $alpha) use (&$alphas): void {
                $alphas[] = $alpha;
            },
            maxUpdatesPerFrame: 3,
        );

        $clock->advance(GameLoop::STEP_SECONDS / 2);
        self::assertSame(0, $loop->tick());
        $clock->advance(10.0);
        self::assertSame(3, $loop->tick());
        self::assertSame(3.0 / 60.0, $loop->elapsedSeconds());
        self::assertEqualsWithDelta(0.5, $alphas[1], 1.0E-9);
        self::assertSame(0, $loop->tick());
        $clock->advance(GameLoop::STEP_SECONDS / 2);
        self::assertSame(1, $loop->tick());
    }

    public function test_it_uses_the_default_update_limit(): void
    {
        $clock = new ManualClock;
        $loop = $this->newLoop($clock);
        $clock->advance(60.0);

        self::assertSame(5, $loop->tick());
        self::assertSame(0, $loop->tick());
    }

    public function test_pause_preserves_the_fraction_and_keeps_rendering_without_advancing_simulation(): void
    {
        $clock = new ManualClock;
        $cooldown = new Cooldown(2.0);
        $cooldown->tryStart();
        $renders = 0;
        $loop = new GameLoop(
            $clock,
            static function (float $dt) use ($cooldown): void {
                $cooldown->advance($dt);
            },
            static function (float $alpha) use (&$renders): void {
                $renders++;
            },
        );

        $clock->advance(GameLoop::STEP_SECONDS / 2);
        $loop->tick();
        $loop->pause();
        $loop->pause();
        self::assertTrue($loop->isPaused());
        $clock->advance(120.0);
        self::assertSame(0, $loop->tick());
        self::assertSame(0.0, $loop->elapsedSeconds());
        self::assertSame(2.0, $cooldown->remainingSeconds());
        self::assertSame(2, $renders);
        $loop->resume();
        $loop->resume();
        self::assertFalse($loop->isPaused());
        self::assertSame(0, $loop->tick());
        $clock->advance(GameLoop::STEP_SECONDS / 2);
        self::assertSame(1, $loop->tick());
        self::assertSame(GameLoop::STEP_SECONDS, $loop->elapsedSeconds());
        self::assertEqualsWithDelta(2.0 - GameLoop::STEP_SECONDS, $cooldown->remainingSeconds(), 1.0E-9);
    }

    public function test_resume_ignores_pause_time_even_without_ticks_during_pause(): void
    {
        $clock = new ManualClock;
        $loop = $this->newLoop($clock);
        $clock->advance(GameLoop::STEP_SECONDS);
        self::assertSame(1, $loop->tick());
        $loop->pause();
        $clock->advance(3600.0);
        $loop->resume();

        self::assertSame(0, $loop->tick());
        $clock->advance(GameLoop::STEP_SECONDS);
        self::assertSame(1, $loop->tick());
        self::assertSame(2.0 / 60.0, $loop->elapsedSeconds());
    }

    public function test_a_pause_requested_by_update_stops_remaining_updates_in_that_frame(): void
    {
        $clock = new ManualClock;
        $loop = null;
        $renders = 0;
        $loop = new GameLoop(
            $clock,
            static function (float $dt) use (&$loop): void {
                self::assertInstanceOf(GameLoop::class, $loop);
                $loop->pause();
            },
            static function (float $alpha) use (&$renders): void {
                $renders++;
            },
        );
        $clock->advance(GameLoop::STEP_SECONDS * 3.5);

        self::assertSame(1, $loop->tick());
        self::assertTrue($loop->isPaused());
        self::assertSame(1, $renders);
        $loop->resume();
        self::assertSame(0, $loop->tick());
        $clock->advance(GameLoop::STEP_SECONDS / 2);
        self::assertSame(1, $loop->tick());
    }

    public function test_the_injected_clock_can_have_an_arbitrary_origin(): void
    {
        $clock = new ManualClock(1000.0);
        $loop = $this->newLoop($clock);

        self::assertSame(0, $loop->tick());
        self::assertSame(0.0, $loop->elapsedSeconds());
        $clock->advance(GameLoop::STEP_SECONDS);
        self::assertSame(1, $loop->tick());
    }

    public function test_logical_inputs_produce_identical_state_at_different_frame_rates(): void
    {
        $baseline = $this->simulate(array_fill(0, 60, 1.0 / 30.0));
        self::assertCount(120, $baseline);

        foreach ([60, 144, 240] as $fps) {
            self::assertSame($baseline, $this->simulate(array_fill(0, $fps * 2, 1.0 / $fps)));
        }

        // Quadro irregular, sem exceder o orçamento: também deve chegar à mesma sequência.
        $frames = array_fill(0, 40, 0.007);
        $frames = array_merge($frames, array_fill(0, 40, 0.033), array_fill(0, 40, 0.010));
        self::assertSame($baseline, $this->simulate($frames));
    }

    #[DataProvider('invalidLimits')]
    public function test_it_rejects_an_invalid_update_limit(int $limit): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->newLoop(new ManualClock, $limit);
    }

    /** @return iterable<string, array{int}> */
    public static function invalidLimits(): iterable
    {
        yield 'zero' => [0];
        yield 'negativo' => [-1];
    }

    #[DataProvider('invalidTimes')]
    public function test_it_rejects_an_invalid_initial_clock_value(float $time): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->newLoop(new ManualClock($time));
    }

    #[DataProvider('invalidTimes')]
    public function test_it_rejects_invalid_clock_values_before_update_or_render(float $time): void
    {
        $clock = new ManualClock;
        $loop = new GameLoop(
            $clock,
            static function (float $dt): void {
                self::fail('Não deve atualizar com relógio inválido.');
            },
            static function (float $alpha): void {
                self::fail('Não deve renderizar com relógio inválido.');
            },
        );
        $clock->set($time);

        $this->expectException(UnexpectedValueException::class);
        $loop->tick();
    }

    /** @return iterable<string, array{float}> */
    public static function invalidTimes(): iterable
    {
        yield 'negativo' => [-1.0];
        yield 'NaN' => [NAN];
        yield 'infinito positivo' => [INF];
        yield 'infinito negativo' => [-INF];
    }

    public function test_it_rejects_a_clock_that_moves_backwards(): void
    {
        $clock = new ManualClock(2.0);
        $loop = $this->newLoop($clock);
        $clock->set(1.0);

        $this->expectException(UnexpectedValueException::class);
        $loop->tick();
    }

    private function newLoop(ManualClock $clock, int $limit = GameLoop::DEFAULT_MAX_UPDATES_PER_FRAME): GameLoop
    {
        return new GameLoop(
            $clock,
            static function (float $dt): void {},
            static function (float $alpha): void {},
            $limit,
        );
    }

    /**
     * Entradas são indexadas pelo passo lógico, não pelo quadro de renderização.
     * O cenário usa regras já existentes; não implementa movimento, arma ou progressão.
     *
     * @param  list<float>  $frames
     * @return list<array{float, float, float}>
     */
    private function simulate(array $frames): array
    {
        $clock = new ManualClock(37.0);
        $health = new Health(20.0);
        $cooldown = new Cooldown(0.25);
        $trace = [];
        $state = 0.0;
        $inputs = [1.0, -2.0, 0.5, 0.0];
        $loop = new GameLoop(
            $clock,
            static function (float $dt) use ($health, $cooldown, $inputs, &$trace, &$state): void {
                $state += $inputs[count($trace) % count($inputs)] * $dt;
                $cooldown->advance($dt);
                if ($cooldown->tryStart()) {
                    $health->damage(1.0);
                }
                $trace[] = [$state, $health->current(), $cooldown->remainingSeconds()];
            },
            static function (float $alpha): void {
                self::assertGreaterThanOrEqual(0.0, $alpha);
                self::assertLessThan(1.0, $alpha);
            },
        );

        foreach ($frames as $duration) {
            $clock->advance($duration);
            $loop->tick();
        }

        self::assertSame(2.0, $loop->elapsedSeconds());

        return $trace;
    }
}
