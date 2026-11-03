<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Infrastructure\State;

use InvalidArgumentException;
use Jogo\Core\GameLoop;
use Jogo\Infrastructure\State\CharacterSelectState;
use Jogo\Infrastructure\State\GameOverState;
use Jogo\Infrastructure\State\GameState;
use Jogo\Infrastructure\State\InstructionsState;
use Jogo\Infrastructure\State\LevelUpState;
use Jogo\Infrastructure\State\MenuState;
use Jogo\Infrastructure\State\PauseState;
use Jogo\Infrastructure\State\PlayState;
use Jogo\Infrastructure\State\RankingState;
use Jogo\Infrastructure\State\ScreenState;
use Jogo\Infrastructure\State\StateLoopSynchronizer;
use Jogo\Infrastructure\State\StateStack;
use Jogo\Tests\Support\ManualClock;
use LogicException;
use PHPUnit\Framework\TestCase;

final class StateStackTest extends TestCase
{
    public function test_push_replace_and_pop_follow_lifecycle_order(): void
    {
        $log = new StateTrace;
        $states = new StateStack;

        $menu = new StateProbe('menu', $log);
        $play = new StateProbe('play', $log);

        $states->push($menu);
        $states->flush();

        $states->replace($play);
        $states->flush();

        $states->pop();
        $states->flush();

        self::assertSame(
            [
                'menu.enter',
                'menu.exit',
                'play.enter',
                'play.exit',
            ],
            $log->entries
        );

        self::assertTrue($states->isEmpty());
    }

    public function test_transition_requested_during_enter_is_deferred(): void
    {
        $log = new StateTrace;
        $states = new StateStack;

        $menu = new StateProbe('menu', $log);
        $next = new StateProbe('next', $log);

        $menu->pushOnEnter = $next;

        $states->push($menu);
        $states->flush();

        self::assertSame(
            ['menu.enter', 'next.enter'],
            $log->entries
        );

        self::assertSame($next, $states->top());
        self::assertSame(2, $states->count());
    }

    public function test_update_only_reaches_top_and_render_draws_overlay(): void
    {
        $log = new StateTrace;
        $states = new StateStack;

        $play = new StateProbe('play', $log);
        $pause = new StateProbe('pause', $log, true);

        $states->push($play);
        $states->flush();

        $states->push($pause);
        $states->flush();

        $states->update(0.016);
        $states->render(0.5);

        self::assertSame(
            [
                'play.enter',
                'pause.enter',
                'pause.update',
                'play.render',
                'pause.render',
            ],
            $log->entries
        );
    }

    public function test_transition_during_update_applies_after_callback(): void
    {
        $log = new StateTrace;
        $states = new StateStack;

        $menu = new StateProbe('menu', $log);
        $next = new StateProbe('next', $log);

        $menu->pushOnUpdate = $next;

        $states->push($menu);
        $states->flush();
        $states->update(0.016);

        self::assertSame(
            [
                'menu.enter',
                'menu.update',
                'next.enter',
            ],
            $log->entries
        );

        self::assertSame($next, $states->top());
    }

    public function test_transition_during_render_does_not_interrupt_frame(): void
    {
        $log = new StateTrace;
        $states = new StateStack;

        $menu = new StateProbe('menu', $log);
        $next = new StateProbe('next', $log);

        $menu->pushOnRender = $next;

        $states->push($menu);
        $states->flush();
        $states->render(0.5);

        self::assertSame(
            [
                'menu.enter',
                'menu.render',
                'next.enter',
            ],
            $log->entries
        );

        self::assertSame($next, $states->top());
    }

    public function test_opaque_screen_hides_previous_screen(): void
    {
        $log = new StateTrace;
        $states = new StateStack;

        $states->push(new StateProbe('menu', $log));
        $states->push(new StateProbe('ranking', $log));

        $states->flush();
        $states->render(0.25);

        self::assertSame(
            [
                'menu.enter',
                'ranking.enter',
                'ranking.render',
            ],
            $log->entries
        );
    }

    public function test_all_planned_screens_have_identifiers(): void
    {
        $screens = [
            new MenuState,
            new CharacterSelectState,
            new PlayState,
            new LevelUpState,
            new PauseState,
            new GameOverState,
            new RankingState,
            new InstructionsState,
        ];

        self::assertSame(
            [
                'menu',
                'character_select',
                'play',
                'level_up',
                'pause',
                'game_over',
                'ranking',
                'instructions',
            ],
            array_map(
                static fn (GameState $state): string => $state->name(),
                $screens
            )
        );

        self::assertTrue((new PauseState)->rendersBelow());
        self::assertTrue((new LevelUpState)->rendersBelow());
        self::assertFalse((new MenuState)->rendersBelow());
    }

    public function test_pause_and_level_up_freeze_logical_time(): void
    {
        $clock = new ManualClock;
        $states = new StateStack;
        $updates = 0;
        $renders = 0;

        $play = new PlayState(
            static function (float $deltaSeconds) use (&$updates): void {
                $updates++;
            },
            static function (float $alpha) use (&$renders): void {
                $renders++;
            },
        );

        $states->push($play);
        $states->flush();

        $loop = new GameLoop(
            $clock,
            static function (float $deltaSeconds) use ($states): void {
                $states->update($deltaSeconds);
            },
            static function (float $alpha) use ($states): void {
                $states->render($alpha);
            },
        );

        $sync = new StateLoopSynchronizer($states, $loop);

        $clock->advance(GameLoop::STEP_SECONDS);
        $loop->tick();

        self::assertSame(1, $updates);

        $states->push(new PauseState);
        $sync->synchronize();

        self::assertTrue($loop->isPaused());

        $clock->advance(30.0);
        $loop->tick();

        self::assertSame(1, $updates);
        self::assertSame(GameLoop::STEP_SECONDS, $loop->elapsedSeconds());

        $states->push(new LevelUpState);
        $sync->synchronize();

        $states->pop();
        $sync->synchronize();

        self::assertTrue($loop->isPaused());

        $states->pop();
        $sync->synchronize();

        self::assertFalse($loop->isPaused());

        $clock->advance(GameLoop::STEP_SECONDS);
        $loop->tick();

        self::assertSame(2, $updates);
        self::assertGreaterThan(0, $renders);
        self::assertEqualsWithDelta(
            GameLoop::STEP_SECONDS * 2,
            $loop->elapsedSeconds(),
            1.0E-9
        );
    }

    public function test_level_up_alone_also_pauses_gameplay(): void
    {
        $clock = new ManualClock;
        $states = new StateStack;

        $states->push(new PlayState);
        $states->push(new LevelUpState);

        $loop = new GameLoop(
            $clock,
            static function (float $dt): void {},
            static function (float $alpha): void {},
        );

        $sync = new StateLoopSynchronizer($states, $loop);
        $sync->synchronize();

        self::assertTrue($loop->isPaused());

        $states->pop();
        $sync->synchronize();

        self::assertFalse($loop->isPaused());
    }

    public function test_invalid_update_time_is_rejected(): void
    {
        $states = new StateStack;

        $this->expectException(InvalidArgumentException::class);

        $states->update(-1.0);
    }

    public function test_invalid_render_alpha_is_rejected(): void
    {
        $states = new StateStack;

        $this->expectException(InvalidArgumentException::class);

        $states->render(1.0);
    }

    public function test_popping_empty_stack_is_rejected(): void
    {
        $states = new StateStack;
        $states->pop();

        $this->expectException(LogicException::class);

        $states->flush();
    }
}

final class StateTrace
{
    /** @var list<string> */
    public array $entries = [];
}

final class StateProbe extends ScreenState
{
    public ?GameState $pushOnEnter = null;

    public ?GameState $pushOnUpdate = null;

    public ?GameState $pushOnRender = null;

    public function __construct(
        private readonly string $label,
        private readonly StateTrace $trace,
        private readonly bool $transparent = false,
    ) {}

    public function name(): string
    {
        return $this->label;
    }

    public function rendersBelow(): bool
    {
        return $this->transparent;
    }

    public function enter(StateStack $states): void
    {
        $this->trace->entries[] = $this->label.'.enter';

        if ($this->pushOnEnter !== null) {
            $next = $this->pushOnEnter;
            $this->pushOnEnter = null;

            $states->push($next);
            $states->flush();
        }
    }

    public function update(float $deltaSeconds, StateStack $states): void
    {
        $this->trace->entries[] = $this->label.'.update';

        if ($this->pushOnUpdate !== null) {
            $next = $this->pushOnUpdate;
            $this->pushOnUpdate = null;

            $states->push($next);
        }
    }

    public function render(float $alpha, StateStack $states): void
    {
        $this->trace->entries[] = $this->label.'.render';

        if ($this->pushOnRender !== null) {
            $next = $this->pushOnRender;
            $this->pushOnRender = null;

            $states->push($next);
        }
    }

    public function exit(StateStack $states): void
    {
        $this->trace->entries[] = $this->label.'.exit';
    }
}
