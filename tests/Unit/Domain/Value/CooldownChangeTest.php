<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Value;

use InvalidArgumentException;
use Jogo\Domain\Value\Cooldown;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CooldownChangeTest extends TestCase
{
    /** @return iterable<string, array{float}> */
    public static function durations(): iterable
    {
        yield 'shorter' => [1.0];
        yield 'longer' => [8.0];
        yield 'unchanged' => [4.0];
    }

    #[DataProvider('durations')]
    public function test_change_preserves_half_remaining_and_never_starts_a_free_attack(float $duration): void
    {
        $cooldown = new Cooldown(4.0);
        $cooldown->tryStart();
        $cooldown->advance(2.0);
        $cooldown->changeDuration($duration);
        self::assertSame($duration, $cooldown->durationSeconds());
        self::assertSame($duration * 0.5, $cooldown->remainingSeconds());
        self::assertFalse($cooldown->tryStart());
        $cooldown->advance($duration * 0.5);
        self::assertTrue($cooldown->isReady());
    }

    public function test_ready_remains_ready_and_fixed_steps_keep_precision_after_a_change(): void
    {
        $cooldown = new Cooldown(4.0);
        $cooldown->changeDuration(2.0);
        self::assertTrue($cooldown->isReady());
        $cooldown->tryStart();
        $cooldown->advance(1.0);
        $cooldown->changeDuration(4.0);
        for ($step = 0; $step < 120; $step++) {
            $cooldown->advance(1.0 / 60.0);
        }
        self::assertTrue($cooldown->isReady());
    }

    /** @return iterable<string, array{float}> */
    public static function invalidDurations(): iterable
    {
        yield 'zero' => [0.0];
        yield 'negative' => [-1.0];
        yield 'nan' => [NAN];
        yield 'inf' => [INF];
    }

    #[DataProvider('invalidDurations')]
    public function test_failure_preserves_duration_and_remaining(float $duration): void
    {
        $cooldown = new Cooldown(4.0);
        $cooldown->tryStart();
        $cooldown->advance(1.0);
        try {
            $cooldown->changeDuration($duration);
            self::fail('Duração inválida deveria falhar.');
        } catch (InvalidArgumentException) {
            self::assertSame(4.0, $cooldown->durationSeconds());
            self::assertSame(3.0, $cooldown->remainingSeconds());
        }
    }

    public function test_underflow_that_would_make_a_blocked_weapon_ready_is_rejected(): void
    {
        $cooldown = new Cooldown(1.0);
        $cooldown->tryStart();
        $cooldown->advance(0.5);
        $this->expectException(InvalidArgumentException::class);
        $cooldown->changeDuration(5.0E-324);
    }
}
