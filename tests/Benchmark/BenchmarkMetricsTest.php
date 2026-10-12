<?php

declare(strict_types=1);

namespace Jogo\Tests\Benchmark;

use InvalidArgumentException;
use Jogo\Application\Benchmark\BenchmarkOptions;
use Jogo\Application\Benchmark\CollisionSummary;
use Jogo\Application\Benchmark\Distribution;
use Jogo\Domain\System\GeometrySnapshot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BenchmarkMetricsTest extends TestCase
{
    public function test_distributions_keep_all_samples_and_use_nearest_rank(): void
    {
        $odd = new Distribution([9, 1, 3]);
        self::assertSame(13.0, $odd->total);
        self::assertSame(13.0 / 3, $odd->mean);
        self::assertSame(3.0, $odd->median);
        self::assertSame(9.0, $odd->maximum);
        self::assertSame(3.5, (new Distribution([6, 1, 2, 5]))->median);
        $hundred = new Distribution(range(1, 100));
        self::assertSame(95.0, $hundred->p95);
        self::assertSame(99.0, $hundred->p99);
        $single = new Distribution([16_600_000]);
        self::assertTrue($single->meetsTimeBudget());
        self::assertSame(16.6, $single->values(1_000_000.0)['p95']);
        self::assertFalse((new Distribution([16_600_001]))->meetsTimeBudget());
        self::assertSame(1000.0, (new Distribution([1_000_000]))->updatesPerSecond());
        self::assertNull((new Distribution([0]))->updatesPerSecond());
    }

    /** @return iterable<string, array{list<int|float>}> */
    public static function invalidSamples(): iterable
    {
        yield 'empty' => [[]];
        yield 'negative' => [[-1]];
        yield 'nan' => [[NAN]];
        yield 'infinite' => [[INF]];
        yield 'overflow' => [[PHP_FLOAT_MAX, PHP_FLOAT_MAX]];
    }

    /** @param list<int|float> $samples */
    #[DataProvider('invalidSamples')]
    public function test_invalid_samples_are_rejected(array $samples): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Distribution($samples);
    }

    public function test_collision_budget_uses_actual_denominator_without_rounding(): void
    {
        $passing = new CollisionSummary([new GeometrySnapshot(100, 39_999, 0, 0, 1)], [1000], 600);
        self::assertTrue($passing->belowBudget);
        self::assertSame(39.999, $passing->perEntity->maximum);
        $failing = new CollisionSummary([new GeometrySnapshot(20, 80, 0, 0, 1)], [2], 601);
        self::assertFalse($failing->belowBudget);
        self::assertSame(40.0, $failing->perEntity->maximum);
        $changing = new CollisionSummary([new GeometrySnapshot(5, 2, 3, 1, 1), new GeometrySnapshot(7, 3, 4, 1, 2)], [3, 2], 601);
        self::assertSame(602, $changing->maximumStep);
        self::assertSame(12, $changing->totalCandidates);
        self::assertSame(14.0, $changing->perStep->total);
        self::assertSame(3.0, $changing->perEntity->mean);
    }

    public function test_negative_geometry_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new GeometrySnapshot(-1, 0, 0, 0, 0);
    }

    public function test_zero_entity_denominator_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CollisionSummary([new GeometrySnapshot(0, 0, 0, 0, 0)], [0], 1);
    }

    public function test_mismatched_collision_samples_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CollisionSummary([new GeometrySnapshot(0, 0, 0, 0, 0)], [], 1);
    }

    public function test_invalid_unit_divisor_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Distribution([1]))->values(0.0);
    }

    public function test_arguments_support_both_forms_and_keep_documented_defaults(): void
    {
        $options = BenchmarkOptions::fromArguments(['--seed', '17', '--warmup=10', '--steps', '20', '--runs=2', '--enemies=30', '--projectiles=40', '--json']);
        self::assertEquals(new BenchmarkOptions(17, 10, 20, 2, 30, 40, true), $options);
        self::assertSame(600, (new BenchmarkOptions)->warmup);
        self::assertSame(1800, (new BenchmarkOptions)->steps);
        self::assertSame(5, (new BenchmarkOptions)->runs);
    }

    /** @return iterable<string, array{list<string>}> */
    public static function invalidArguments(): iterable
    {
        foreach ([['--unknown'], ['--seed'], ['--steps=0'], ['--warmup=-1'], ['--runs=0'], ['--enemies=0'], ['--projectiles=201'], ['--projectiles=0'], ['--steps=1.5'], ['--json=true'], ['--steps=1', '--steps=2'], ['--seed=9999999999999999999999'], ['--warmup='.PHP_INT_MAX, '--steps=1']] as $index => $args) {
            yield (string) $index => [$args];
        }
    }

    /** @param list<string> $arguments */
    #[DataProvider('invalidArguments')]
    public function test_invalid_arguments_are_rejected(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);
        BenchmarkOptions::fromArguments($arguments);
    }
}
