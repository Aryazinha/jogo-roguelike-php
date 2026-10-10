<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Value;

use InvalidArgumentException;
use Jogo\Domain\Value\Cooldown;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CooldownTest extends TestCase
{
    public function test_it_starts_ready_and_prevents_a_second_use(): void
    {
        $cooldown = new Cooldown(2.0);

        self::assertTrue($cooldown->isReady());
        self::assertTrue($cooldown->tryStart());
        self::assertFalse($cooldown->isReady());
        self::assertFalse($cooldown->tryStart());
        self::assertSame(2.0, $cooldown->remainingSeconds());
    }

    public function test_it_becomes_ready_after_its_duration(): void
    {
        $cooldown = new Cooldown(2.0);
        $cooldown->tryStart();

        $cooldown->advance(0.75);
        self::assertSame(1.25, $cooldown->remainingSeconds());
        self::assertFalse($cooldown->isReady());

        $cooldown->advance(5.0);
        self::assertSame(0.0, $cooldown->remainingSeconds());
        self::assertTrue($cooldown->isReady());
    }

    public function test_it_can_be_reset_immediately(): void
    {
        $cooldown = new Cooldown(2.0);
        $cooldown->tryStart();

        $cooldown->reset();

        self::assertTrue($cooldown->isReady());
    }

    public function test_it_rejects_an_invalid_duration(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Cooldown(0.0);
    }

    public function test_it_rejects_a_negative_time_step(): void
    {
        $cooldown = new Cooldown(2.0);

        $this->expectException(InvalidArgumentException::class);

        $cooldown->advance(-0.1);
    }

    /** @return iterable<string, array{float, int}> */
    public static function fixedDurations(): iterable
    {
        yield 'two seconds' => [2.0, 120];
        yield 'one point eight seconds' => [1.8, 108];
        yield 'half second' => [0.5, 30];
        yield 'zero point eight seconds' => [0.8, 48];
    }

    #[DataProvider('fixedDurations')]
    public function test_fixed_steps_expire_at_the_configured_duration_without_an_extra_step(float $duration, int $steps): void
    {
        $cooldown = new Cooldown($duration);
        $cooldown->tryStart();
        for ($step = 1; $step < $steps; $step++) {
            $cooldown->advance(1.0 / 60.0);
            self::assertFalse($cooldown->isReady());
        }
        $before = $cooldown->remainingSeconds();
        $cooldown->advance(0.0);
        self::assertSame($before, $cooldown->remainingSeconds());
        $cooldown->advance(1.0 / 60.0);
        self::assertTrue($cooldown->isReady());
        self::assertSame(0.0, $cooldown->remainingSeconds());
        self::assertTrue($cooldown->tryStart());
        self::assertSame($duration, $cooldown->remainingSeconds());
    }

    public function test_tiny_cooldowns_do_not_expire_early_and_reset_discards_previous_progress(): void
    {
        $cooldown = new Cooldown(2.0E-12);
        $cooldown->tryStart();
        $cooldown->advance(1.0E-12);
        self::assertFalse($cooldown->isReady());
        self::assertSame(1.0E-12, $cooldown->remainingSeconds());
        $cooldown->reset();
        $cooldown->tryStart();
        $cooldown->advance(1.0E-12);
        self::assertFalse($cooldown->isReady());
        $cooldown->advance(1.0E-12);
        self::assertTrue($cooldown->isReady());
    }
}
