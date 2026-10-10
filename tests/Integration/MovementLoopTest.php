<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Core\GameLoop;
use Jogo\Domain\System\MovementSystem;
use Jogo\Domain\Value\Facing;
use Jogo\Domain\Value\InputState;
use Jogo\Domain\Value\MapBounds;
use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Input\ScriptedInput;
use Jogo\Tests\Support\FakeMovable;
use Jogo\Tests\Support\ManualClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MovementLoopTest extends TestCase
{
    public function test_frame_input_is_reused_by_fixed_steps_and_render_does_not_move(): void
    {
        $clock = new ManualClock;
        $target = new FakeMovable(Vector2::zero());
        $facing = new Facing(new Vector2(0.0, -1.0));
        $system = new MovementSystem(new MapBounds(-100.0, -100.0, 100.0, 100.0));
        $input = new ScriptedInput([new InputState(new Vector2(1.0, 0.0), new Vector2(1.0, 0.0))]);
        $state = $input->poll();
        $dts = [];
        $renderPositions = [];
        $loop = new GameLoop(
            $clock,
            static function (float $dt) use ($target, $facing, $system, $state, &$dts): void {
                $dts[] = $dt;
                $system->update($target, $facing, $state, 60.0, $dt, true);
            },
            static function (float $alpha) use ($target, &$renderPositions): void {
                $renderPositions[] = $target->position();
            },
        );

        $clock->advance(GameLoop::STEP_SECONDS / 2.0);
        self::assertSame(0, $loop->tick());
        self::assertTrue($target->position()->equals(Vector2::zero()));
        $clock->advance(GameLoop::STEP_SECONDS * 2.5);
        self::assertSame(3, $loop->tick());
        self::assertSame(array_fill(0, 3, GameLoop::STEP_SECONDS), $dts);
        self::assertTrue($target->position()->equals(new Vector2(3.0, 0.0)));
        self::assertCount(2, $renderPositions);
        self::assertTrue($renderPositions[1]->equals(new Vector2(3.0, 0.0)));

        $position = $target->position();
        self::assertSame(0, $loop->tick());
        self::assertSame($position, $target->position());
    }

    public function test_pause_freezes_movement_and_look_then_resume_excludes_pause_time(): void
    {
        $clock = new ManualClock;
        $target = new FakeMovable(Vector2::zero());
        $facing = new Facing(new Vector2(0.0, -1.0));
        $system = new MovementSystem(new MapBounds(-100.0, -100.0, 100.0, 100.0));
        $script = new ScriptedInput([
            new InputState(new Vector2(1.0, 0.0), new Vector2(1.0, 0.0)),
            new InputState(new Vector2(0.0, 1.0), new Vector2(0.0, 1.0)),
        ]);
        $renders = 0;
        $loop = new GameLoop(
            $clock,
            static function (float $dt) use ($target, $facing, $system, $script): void {
                $system->update($target, $facing, $script->poll(), 60.0, $dt, true);
            },
            static function (float $alpha) use (&$renders): void {
                $renders++;
            },
        );

        $clock->advance(GameLoop::STEP_SECONDS);
        self::assertSame(1, $loop->tick());
        $position = $target->position();
        $look = $facing->direction();
        $loop->pause();
        $clock->advance(120.0);
        self::assertSame(0, $loop->tick());
        self::assertSame($position, $target->position());
        self::assertSame($look, $facing->direction());
        self::assertSame(2, $renders);
        $loop->resume();
        self::assertSame(0, $loop->tick());
        $clock->advance(GameLoop::STEP_SECONDS);
        self::assertSame(1, $loop->tick());
        self::assertTrue($target->position()->equals(new Vector2(1.0, 1.0)));
        self::assertTrue($facing->direction()->equals(new Vector2(0.0, 1.0)));
    }

    /** @param list<float> $frameDurations */
    #[DataProvider('frameSchedules')]
    public function test_the_entire_logical_path_is_identical_across_frame_rates(array $frameDurations): void
    {
        $expected = $this->simulate(array_fill(0, 120, GameLoop::STEP_SECONDS));
        $actual = $this->simulate($frameDurations);

        self::assertCount(120, $actual);
        self::assertSame($expected, $actual);
        // O caminho inclui limites, diagonais, repouso e mudança independente do olhar.
        self::assertSame(15.0, $actual[29][0]);
        self::assertSame(12.0, $actual[29][1]);
        self::assertSame(-20.0, $actual[89][0]);
        self::assertSame(-15.0, $actual[89][1]);
        self::assertSame($actual[104], $actual[119]);
    }

    /** @return iterable<string, array{list<float>}> */
    public static function frameSchedules(): iterable
    {
        foreach ([30, 60, 144, 240] as $fps) {
            yield "$fps FPS" => [array_fill(0, $fps * 2, 1.0 / $fps)];
        }

        $irregular = [];

        for ($cycle = 0; $cycle < 40; $cycle++) {
            array_push($irregular, 0.007, 0.033, 0.010);
        }

        yield 'quadros irregulares' => [$irregular];
    }

    /**
     * A sequência é indexada por passo lógico para comparar as mesmas intenções.
     * A entrada real continuará sendo consultada por quadro, conforme a A1.
     *
     * @param  list<float>  $frameDurations
     * @return list<array{float, float, float, float}>
     */
    private function simulate(array $frameDurations): array
    {
        $clock = new ManualClock;
        $target = new FakeMovable(Vector2::zero());
        $facing = new Facing(new Vector2(0.0, -1.0));
        $system = new MovementSystem(new MapBounds(-20.0, -15.0, 15.0, 12.0));
        $states = [
            new InputState(new Vector2(1.0, 0.0)),
            new InputState(new Vector2(1.0, 1.0), new Vector2(1.0, 0.0)),
            new InputState(new Vector2(0.0, -1.0)),
            new InputState(Vector2::zero(), new Vector2(-1.0, 0.0)),
            new InputState(new Vector2(-1.0, -1.0)),
            new InputState(new Vector2(-1.0, -1.0)),
            new InputState(new Vector2(0.3, 0.4), new Vector2(0.0, 1.0)),
            new InputState(Vector2::zero()),
        ];
        $sequence = [];

        foreach ($states as $state) {
            array_push($sequence, ...array_fill(0, 15, $state));
        }

        $script = new ScriptedInput($sequence);
        $trace = [];
        $loop = new GameLoop(
            $clock,
            static function (float $dt) use ($target, $facing, $system, $script, &$trace): void {
                $system->update($target, $facing, $script->poll(), 120.0, $dt, true);
                $trace[] = [$target->position()->x(), $target->position()->y(),
                    $facing->direction()->x(), $facing->direction()->y()];
            },
            static function (float $alpha): void {},
        );

        foreach ($frameDurations as $duration) {
            $clock->advance($duration);
            $loop->tick();
        }

        self::assertSame(2.0, $loop->elapsedSeconds());

        return $trace;
    }
}
