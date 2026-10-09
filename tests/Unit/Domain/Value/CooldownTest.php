<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Value;

use InvalidArgumentException;
use Jogo\Domain\Value\Cooldown;
use PHPUnit\Framework\TestCase;

final class CooldownTest extends TestCase
{
    public function testItStartsReadyAndPreventsASecondUse(): void
    {
        $cooldown = new Cooldown(2.0);

        self::assertTrue($cooldown->isReady());
        self::assertTrue($cooldown->tryStart());
        self::assertFalse($cooldown->isReady());
        self::assertFalse($cooldown->tryStart());
        self::assertSame(2.0, $cooldown->remainingSeconds());
    }

    public function testItBecomesReadyAfterItsDuration(): void
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

    public function testItCanBeResetImmediately(): void
    {
        $cooldown = new Cooldown(2.0);
        $cooldown->tryStart();

        $cooldown->reset();

        self::assertTrue($cooldown->isReady());
    }

    public function testItRejectsAnInvalidDuration(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Cooldown(0.0);
    }

    public function testItRejectsANegativeTimeStep(): void
    {
        $cooldown = new Cooldown(2.0);

        $this->expectException(InvalidArgumentException::class);

        $cooldown->advance(-0.1);
    }
}
