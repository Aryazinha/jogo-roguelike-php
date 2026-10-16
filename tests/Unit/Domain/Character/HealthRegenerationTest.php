<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Character;

use InvalidArgumentException;
use Jogo\Domain\Character\HealthRegeneration;
use Jogo\Domain\Value\Health;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HealthRegenerationTest extends TestCase
{
    /** @return iterable<string, array{int}> */
    public static function frameRates(): iterable
    {
        yield '30 FPS' => [30];
        yield '60 FPS' => [60];
        yield '144 FPS' => [144];
        yield '240 FPS' => [240];
    }

    #[DataProvider('frameRates')]
    public function test_steve_heals_exactly_on_the_thirty_second_step_at_any_frame_rate(int $fps): void
    {
        $health = new Health(20.0, 15.0);
        $regeneration = new HealthRegeneration(1.0, 30.0);
        $step = 1.0 / $fps;

        for ($frame = 1; $frame < 30 * $fps; $frame++) {
            self::assertSame(0.0, $regeneration->advance($health, $step), "Curou cedo no quadro {$frame}.");
        }
        self::assertSame(1.0, $regeneration->advance($health, $step));
        self::assertSame(16.0, $health->current());
    }

    #[DataProvider('frameRates')]
    public function test_alex_heals_four_times_in_one_minute_at_any_frame_rate(int $fps): void
    {
        $health = new Health(16.0, 10.0);
        $regeneration = new HealthRegeneration(1.0, 15.0);

        $restored = 0.0;
        for ($frame = 0; $frame < 60 * $fps; $frame++) {
            $restored += $regeneration->advance($health, 1.0 / $fps);
        }

        self::assertSame(4.0, $restored);
        self::assertSame(14.0, $health->current());
    }

    public function test_irregular_frames_produce_the_same_result(): void
    {
        $health = new Health(16.0, 10.0);
        $regeneration = new HealthRegeneration(1.0, 15.0);

        foreach ([0.5, 3.25, 0.016, 7.234, 3.75] as $delta) {
            self::assertSame(0.0, $regeneration->advance($health, $delta));
        }
        self::assertSame(1.0, $regeneration->advance($health, 0.25));
        self::assertSame(11.0, $health->current());
    }

    public function test_one_large_step_applies_every_due_heal(): void
    {
        $health = new Health(16.0, 10.0);
        $regeneration = new HealthRegeneration(1.0, 15.0);

        self::assertSame(3.0, $regeneration->advance($health, 45.0));
        self::assertSame(13.0, $health->current());
    }

    public function test_healing_never_exceeds_the_maximum(): void
    {
        $health = new Health(20.0, 19.5);
        $regeneration = new HealthRegeneration(1.0, 30.0);

        self::assertSame(0.5, $regeneration->advance($health, 120.0));
        self::assertSame(20.0, $health->current());
        self::assertSame(0.0, $regeneration->elapsedSeconds());
    }

    public function test_clock_does_not_run_while_health_is_full(): void
    {
        $health = new Health(20.0);
        $regeneration = new HealthRegeneration(1.0, 30.0);

        self::assertSame(0.0, $regeneration->advance($health, 100.0));
        self::assertSame(0.0, $regeneration->elapsedSeconds());

        $health->damage(1.0);
        self::assertSame(0.0, $regeneration->advance($health, 29.5));
        self::assertSame(1.0, $regeneration->advance($health, 0.5));
    }

    public function test_dead_entity_does_not_regenerate(): void
    {
        $health = new Health(20.0, 0.0);
        $regeneration = new HealthRegeneration(1.0, 30.0);

        self::assertSame(0.0, $regeneration->advance($health, 300.0));
        self::assertTrue($health->isDead());
    }

    public function test_zero_delta_changes_nothing(): void
    {
        $health = new Health(20.0, 10.0);
        $regeneration = new HealthRegeneration(1.0, 30.0);

        self::assertSame(0.0, $regeneration->advance($health, 0.0));
        self::assertSame(0.0, $regeneration->elapsedSeconds());
    }

    public function test_negative_or_infinite_delta_is_rejected(): void
    {
        $regeneration = new HealthRegeneration(1.0, 30.0);

        $this->expectException(InvalidArgumentException::class);
        $regeneration->advance(new Health(20.0, 10.0), -0.1);
    }

    public function test_invalid_configuration_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('regeneracao.intervaloSegundos deve ser maior que zero.');
        new HealthRegeneration(1.0, 0.0);
    }
}
