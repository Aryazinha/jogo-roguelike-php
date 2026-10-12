<?php

declare(strict_types=1);

namespace Jogo\Tests\Benchmark;

use Jogo\Application\Benchmark\BenchmarkOptions;
use Jogo\Application\Benchmark\BenchmarkRunner;
use Jogo\Domain\Weapon\WeaponDefinition;
use Jogo\Tests\Support\FixedMeasurement;
use PHPUnit\Framework\TestCase;

final class BenchmarkRegressionTest extends TestCase
{
    public function test_long_integration_has_identical_state_events_metrics_and_checksum_at_all_frame_rates(): void
    {
        // 40 segundos lógicos: inclui aquecimento, roteiro repetido e duas evoluções.
        $options = new BenchmarkOptions(runs: 1);
        $runner = BenchmarkRunner::fromProject(dirname(__DIR__, 2), new FixedMeasurement);
        $expected = $runner->run($options, captureEvents: true)->runs[0];
        foreach ([[1.0 / 30.0], [1.0 / 60.0], [1.0 / 144.0], [1.0 / 240.0], [1.0 / 120.0, 1.0 / 40.0, 1.0 / 90.0, 1.0 / 30.0]] as $frames) {
            $actual = BenchmarkRunner::fromProject(dirname(__DIR__, 2), new FixedMeasurement(9000))->run($options, frames: $frames, captureEvents: true)->runs[0];
            self::assertSame(json_encode($expected->state, JSON_THROW_ON_ERROR), json_encode($actual->state, JSON_THROW_ON_ERROR));
            self::assertSame($expected->events, $actual->events);
            self::assertEquals($expected->logical, $actual->logical);
            self::assertEquals($expected->collisions, $actual->collisions);
            self::assertSame($expected->checksum, $actual->checksum);
            self::assertSame($expected->eventChecksum, $actual->eventChecksum);
            self::assertSame(9000.0, $actual->timing->p95);
        }
        $weapons = $expected->state['weapons'];
        self::assertIsArray($weapons);
        self::assertIsArray($weapons[0]);
        self::assertIsArray($weapons[2]);
        self::assertInstanceOf(WeaponDefinition::class, $weapons[0][0]);
        self::assertInstanceOf(WeaponDefinition::class, $weapons[2][0]);
        self::assertSame('enchanted_stone_sword', $weapons[0][0]->id);
        self::assertSame('mega_tnt', $weapons[2][0]->id);
        self::assertGreaterThan(0, $expected->logical->impacts);
        self::assertGreaterThan(0, $expected->logical->contacts);
        self::assertSame(0, $expected->logical->deaths);
        self::assertSame(200, $expected->logical->poolAtEnd->peakActive);
        self::assertSame($expected->logical->poolAfterCleanup->acquisitions, $expected->logical->poolAfterCleanup->releases);
    }

    public function test_prepared_index_preserves_baseline_geometry_and_gameplay(): void
    {
        $runner = BenchmarkRunner::fromProject(dirname(__DIR__, 2), new FixedMeasurement);
        $baseline = $runner->run(new BenchmarkOptions(warmup: 30, steps: 150, runs: 1, freshQueries: true), captureEvents: true)->runs[0];
        $prepared = $runner->run(new BenchmarkOptions(warmup: 30, steps: 150, runs: 1, freshQueries: false), captureEvents: true)->runs[0];
        self::assertSame(json_encode($baseline->state, JSON_THROW_ON_ERROR), json_encode($prepared->state, JSON_THROW_ON_ERROR));
        self::assertSame($baseline->events, $prepared->events);
        self::assertEquals($baseline->logical, $prepared->logical);
        self::assertSame($baseline->checksum, $prepared->checksum);
        self::assertSame($baseline->collisions->circles, $prepared->collisions->circles);
        self::assertSame($baseline->collisions->segments, $prepared->collisions->segments);
        self::assertSame($baseline->collisions->sectors, $prepared->collisions->sectors);
        self::assertSame($baseline->collisions->totalCandidates, $prepared->collisions->totalCandidates);
        self::assertSame(150, $prepared->collisions->rebuilds);
        self::assertGreaterThan($prepared->collisions->rebuilds, $baseline->collisions->rebuilds);
    }
}
