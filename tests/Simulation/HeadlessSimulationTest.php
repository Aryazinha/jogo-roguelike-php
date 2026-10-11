<?php

declare(strict_types=1);

namespace Jogo\Tests\Simulation;

use InvalidArgumentException;
use Jogo\Application\Simulation\HeadlessSimulation;
use Jogo\Application\Simulation\SimulationClock;
use Jogo\Application\Simulation\SimulationMetrics;
use Jogo\Application\Simulation\SimulationOptions;
use Jogo\Application\Simulation\SimulationReport;
use Jogo\Application\Simulation\UpdateTiming;
use Jogo\Core\Config\WeaponConfigLoader;
use Jogo\Core\GameLoop;
use Jogo\Domain\Pool\PoolSnapshot;
use Jogo\Domain\System\ProjectileCollisionSnapshot;
use Jogo\Domain\Value\InputState;
use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Input\NeutralInput;
use Jogo\Infrastructure\Input\ScriptedInput;
use Jogo\Tests\Support\FixedMeasurement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use UnexpectedValueException;

final class HeadlessSimulationTest extends TestCase
{
    /** @return iterable<string, array{list<float>}> */
    public static function schedules(): iterable
    {
        foreach ([30, 60, 144, 240] as $fps) {
            yield "$fps FPS" => [[1.0 / $fps]];
        }
        yield 'irregular' => [[1.0 / 120.0, 1.0 / 40.0, 1.0 / 90.0, 1.0 / 30.0]];
    }

    /** @param list<float> $frames */
    #[DataProvider('schedules')]
    public function test_full_logical_metrics_and_checksum_are_reproducible_at_different_frame_rates(array $frames): void
    {
        $options = new SimulationOptions;
        $baseline = self::simulation()->run($options);
        $other = self::simulation(9000)->run($options, $frames);
        self::assertSame($baseline->checksum, $other->checksum);
        self::assertEquals($baseline->logical, $other->logical);
        self::assertSame(600, $other->logical->steps);
        self::assertSame(10.0, $other->logical->elapsedSeconds);
        self::assertSame(600000, $baseline->timing->totalNanoseconds);
        self::assertSame(5400000, $other->timing->totalNanoseconds);
        self::assertSame(9000.0, $other->timing->averageNanoseconds());
        self::assertSame(9000, $other->timing->maxNanoseconds);
        self::assertGreaterThan(0, $other->logical->impacts);
        self::assertGreaterThan(0, $other->logical->deaths);
        self::assertSame(0, $other->logical->poolAfterCleanup->active);
        self::assertSame(200, $other->logical->poolAfterCleanup->available);
        self::assertSame($other->logical->poolAtEnd->acquisitions, $other->logical->poolAfterCleanup->releases);
    }

    public function test_seed_changes_logical_state_and_repeated_runs_on_same_service_are_isolated(): void
    {
        $simulation = self::simulation();
        $first = $simulation->run(new SimulationOptions(seed: 123, steps: 120));
        $second = $simulation->run(new SimulationOptions(seed: 124, steps: 120));
        $third = $simulation->run(new SimulationOptions(seed: 123, steps: 120, json: true));
        self::assertNotSame($first->checksum, $second->checksum);
        self::assertSame($first->checksum, $third->checksum);
        self::assertEquals($first->logical, $third->logical);
        self::assertSame(120, $third->timing->samples);
    }

    public function test_no_targets_is_valid_and_does_not_create_attacks_or_collision_checks(): void
    {
        $report = self::simulation()->run(new SimulationOptions(steps: 5, enemies: 0));
        self::assertSame(0, $report->logical->acceptedAttacks);
        self::assertSame(0, $report->logical->impacts);
        self::assertSame(0, $report->logical->collisions->totalCandidates);
        self::assertSame(0, $report->logical->poolAtEnd->acquisitions);
        self::assertSame(200, $report->logical->poolAfterCleanup->available);
    }

    public function test_neutral_input_always_returns_the_same_immutable_neutral_state(): void
    {
        $input = new NeutralInput;
        $state = $input->poll();
        self::assertSame($state, $input->poll());
        self::assertEquals(Vector2::zero(), $state->movement);
        self::assertNull($state->facing);
        self::assertFalse($state->pauseRequested);
        self::assertTrue((new ReflectionClass($state))->isReadOnly());
        foreach ([SimulationMetrics::class, SimulationReport::class, UpdateTiming::class, PoolSnapshot::class, ProjectileCollisionSnapshot::class] as $class) {
            self::assertTrue((new ReflectionClass($class))->isReadOnly());
        }
    }

    public function test_non_neutral_input_is_explicitly_rejected(): void
    {
        $weapons = (new WeaponConfigLoader)->load(dirname(__DIR__, 2).'/config/weapons.json');
        $simulation = new HeadlessSimulation($weapons, new ScriptedInput([new InputState(new Vector2(1.0, 0.0))]), new FixedMeasurement);
        $this->expectException(InvalidArgumentException::class);
        $simulation->run(new SimulationOptions(steps: 1));
    }

    public function test_injected_measurement_clock_cannot_go_backward(): void
    {
        $this->expectException(UnexpectedValueException::class);
        self::simulation(-1)->run(new SimulationOptions(steps: 1));
    }

    public function test_reports_have_valid_text_json_units_and_checksum_without_timing(): void
    {
        $report = self::simulation()->run(new SimulationOptions(steps: 120, enemies: 8));
        self::assertStringContainsString('Passos: 120 | tempo lógico: 2.000000 s', $report->toText());
        self::assertStringContainsString('Após limpeza: 0 ativos, 200 disponíveis', $report->toText());
        $json = json_decode($report->toJson(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($json);
        self::assertSame($report->checksum, $json['checksum']);
        self::assertIsArray($json['timing']);
        self::assertSame('nanoseconds', $json['timing']['unit']);
        self::assertSame(64, strlen($report->checksum));
    }

    /** @return iterable<string, array{list<string>}> */
    public static function invalidArguments(): iterable
    {
        foreach ([['--unknown'], ['--steps'], ['--seed', '--json'], ['--steps', '0'], ['--steps=-1'], ['--enemies=-1'],
            ['--seed=1.5'], ['--steps=1.5'], ['--steps=999999999999999999999999'], ['--seconds=NaN'], ['--seconds=INF'],
            ['--seconds=1e300'], ['--seconds=-1'], ['--seconds=0'], ['--json=true'], ['--json', '--json'],
            ['--seed=1', '--seed=2'], ['--steps=1', '--seconds=2'], ['bare']] as $index => $arguments) {
            yield (string) $index => [$arguments];
        }
    }

    /** @param list<string> $arguments */
    #[DataProvider('invalidArguments')]
    public function test_invalid_cli_arguments_are_rejected(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);
        SimulationOptions::fromArguments($arguments);
    }

    public function test_cli_defaults_and_seconds_are_converted_to_fixed_steps(): void
    {
        self::assertEquals(new SimulationOptions, SimulationOptions::fromArguments([]));
        $options = SimulationOptions::fromArguments(['--seed', '-2', '--seconds=2.5', '--enemies', '0', '--json']);
        self::assertSame([-2, 150, 0, true], array_values(get_object_vars($options)));
        self::assertSame(2, SimulationOptions::fromArguments(['--seconds=0.02'])->steps);
        self::assertSame(0.0, (new UpdateTiming(0, 0, 0))->averageNanoseconds());
    }

    /** @return iterable<string, array{list<float>}> */
    public static function invalidFrames(): iterable
    {
        yield 'empty' => [[]];
        yield 'zero' => [[0.0]];
        yield 'negative' => [[-1.0]];
        yield 'nan' => [[NAN]];
        yield 'inf' => [[INF]];
    }

    /** @param list<float> $frames */
    #[DataProvider('invalidFrames')]
    public function test_invalid_frame_schedules_fail(array $frames): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::simulation()->run(new SimulationOptions(steps: 1), $frames);
    }

    public function test_controlled_clock_and_fixed_step_are_independent_of_wall_time(): void
    {
        $clock = new SimulationClock;
        $steps = [];
        $loop = new GameLoop($clock, static function (float $dt) use (&$steps): void {
            $steps[] = $dt;
        }, static function (float $alpha): void {});
        $clock->advance(GameLoop::STEP_SECONDS * 2.0);
        self::assertSame(2, $loop->tick());
        self::assertSame([GameLoop::STEP_SECONDS, GameLoop::STEP_SECONDS], $steps);
        $loop->pause();
        $clock->advance(100.0);
        self::assertSame(0, $loop->tick());
        self::assertSame(GameLoop::STEP_SECONDS * 2.0, $loop->elapsedSeconds());
    }

    private static function simulation(int $measurementInterval = 1000): HeadlessSimulation
    {
        $weapons = (new WeaponConfigLoader)->load(dirname(__DIR__, 2).'/config/weapons.json');

        return new HeadlessSimulation($weapons, new NeutralInput, new FixedMeasurement($measurementInterval));
    }
}
