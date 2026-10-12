<?php

declare(strict_types=1);

namespace Jogo\Tests\Benchmark;

use InvalidArgumentException;
use Jogo\Application\Benchmark\BenchmarkOptions;
use Jogo\Application\Benchmark\BenchmarkReport;
use Jogo\Application\Benchmark\BenchmarkRunner;
use Jogo\Application\Simulation\MeasurementClock;
use Jogo\Tests\Support\FixedMeasurement;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class BenchmarkIntegrationTest extends TestCase
{
    public function test_real_systems_reach_capacity_reuse_objects_and_cleanup(): void
    {
        $runner = BenchmarkRunner::fromProject(dirname(__DIR__, 2), new FixedMeasurement);
        $configs = glob(dirname(__DIR__, 2).'/config/*.json');
        self::assertIsArray($configs);
        $before = array_map(static fn (string $path): string|false => hash_file('sha256', $path), $configs);
        $report = $runner->run(new BenchmarkOptions(warmup: 2, steps: 6, runs: 2), captureEvents: true);
        self::assertSame(0, $report->exitCode());
        self::assertSame(12, $report->timing->count);
        self::assertSame(1000.0, $report->timing->p95);
        self::assertSame($report->runs[0]->checksum, $report->runs[1]->checksum);
        self::assertSame($report->runs[0]->events, $report->runs[1]->events);
        self::assertGreaterThan(0, $report->runs[0]->logical->impacts);
        self::assertSame(300, $report->runs[0]->logical->minimumEnemies);
        self::assertSame(200, $report->runs[0]->logical->measuredProjectilePeak);
        self::assertGreaterThan(200, $report->runs[0]->logical->poolAtEnd->acquisitions);
        self::assertSame(0, $report->runs[0]->logical->poolAfterCleanup->active);
        self::assertSame(200, $report->runs[0]->logical->poolAfterCleanup->available);
        $visual = $report->values()['visualFps'];
        self::assertIsArray($visual);
        self::assertSame('not_evaluated', $visual['status']);
        self::assertIsArray(json_decode($report->toJson(), true, 512, JSON_THROW_ON_ERROR));
        self::assertStringContainsString('p95:', $report->toText());
        self::assertSame($before, array_map(static fn (string $path): string|false => hash_file('sha256', $path), $configs));
    }

    public function test_time_failure_does_not_affect_logical_state(): void
    {
        $options = new BenchmarkOptions(warmup: 0, steps: 6, runs: 1);
        $fast = BenchmarkRunner::fromProject(dirname(__DIR__, 2), new FixedMeasurement)->run($options);
        $slow = BenchmarkRunner::fromProject(dirname(__DIR__, 2), new FixedMeasurement(16_600_001))->run($options);
        self::assertSame($fast->runs[0]->checksum, $slow->runs[0]->checksum);
        self::assertSame(2, $slow->exitCode());
        self::assertFalse($slow->acceptance()['p95AtMost16.6ms']);
    }

    public function test_report_detects_divergent_repetitions(): void
    {
        $runner = BenchmarkRunner::fromProject(dirname(__DIR__, 2), new FixedMeasurement);
        $one = $runner->run(new BenchmarkOptions(seed: 1, warmup: 0, steps: 6, runs: 1, enemies: 3, projectiles: 2))->runs[0];
        $two = $runner->run(new BenchmarkOptions(seed: 2, warmup: 0, steps: 6, runs: 1, enemies: 3, projectiles: 2))->runs[0];
        $report = new BenchmarkReport(new BenchmarkOptions(warmup: 0, steps: 6, runs: 2, enemies: 3, projectiles: 2), [$one, $two], []);
        self::assertFalse($report->acceptance()['identicalChecksums']);
        self::assertSame(2, $report->exitCode());
    }

    public function test_empty_report_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new BenchmarkReport(new BenchmarkOptions, [], []);
    }

    public function test_regressing_measurement_clock_is_rejected(): void
    {
        $clock = new class implements MeasurementClock
        {
            private int $time = 10;

            public function nowNanoseconds(): int
            {
                return --$this->time;
            }
        };
        $runner = BenchmarkRunner::fromProject(dirname(__DIR__, 2), $clock);
        $this->expectException(UnexpectedValueException::class);
        $runner->run(new BenchmarkOptions(warmup: 0, steps: 1, runs: 1, enemies: 3, projectiles: 2));
    }
}
