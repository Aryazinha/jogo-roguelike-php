<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Value;

use InvalidArgumentException;
use Jogo\Domain\Value\Cooldown;
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
}
