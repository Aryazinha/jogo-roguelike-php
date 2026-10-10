<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Weapon;

use InvalidArgumentException;
use Jogo\Domain\Weapon\Weapon;
use Jogo\Domain\Weapon\WeaponDefinition;
use Jogo\Tests\Support\WeaponFixtures;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WeaponTest extends TestCase
{
    public function test_first_update_attempts_automatically_and_accepted_execution_starts_cooldown(): void
    {
        $definition = WeaponFixtures::definition();
        $attempts = [];
        $weapon = new Weapon($definition, static function (WeaponDefinition $received) use (&$attempts): bool {
            $attempts[] = $received;

            return true;
        });
        self::assertSame($definition, $weapon->definition());
        self::assertTrue($weapon->isReady());
        self::assertSame(0.0, $weapon->remainingSeconds());
        self::assertTrue($weapon->update(0.0));
        self::assertSame([$definition], $attempts);
        self::assertFalse($weapon->isReady());
        self::assertSame(2.0, $weapon->remainingSeconds());
        self::assertFalse($weapon->update(0.0));
        self::assertFalse($weapon->update(0.75));
        self::assertSame(1.25, $weapon->remainingSeconds());
        self::assertCount(1, $attempts);
        self::assertTrue($weapon->update(1.25));
        self::assertCount(2, $attempts);
        self::assertSame(2.0, $weapon->remainingSeconds());
    }

    public function test_rejected_attempt_remains_ready_until_the_executor_accepts(): void
    {
        $accept = false;
        $attempts = 0;
        $weapon = new Weapon(WeaponFixtures::definition(), static function (WeaponDefinition $definition) use (&$accept, &$attempts): bool {
            $attempts++;

            return $accept;
        });
        self::assertFalse($weapon->update(1.0));
        self::assertTrue($weapon->isReady());
        self::assertSame(0.0, $weapon->remainingSeconds());
        self::assertFalse($weapon->update(0.0));
        $accept = true;
        self::assertTrue($weapon->update(0.0));
        self::assertSame(3, $attempts);
        self::assertSame(2.0, $weapon->remainingSeconds());
    }

    public function test_large_dt_performs_at_most_one_attempt_and_does_not_recover_missed_attacks(): void
    {
        $attempts = 0;
        $weapon = new Weapon(WeaponFixtures::definition(), static function (WeaponDefinition $definition) use (&$attempts): bool {
            $attempts++;

            return true;
        });
        self::assertTrue($weapon->update(1000.0));
        self::assertSame(1, $attempts);
        self::assertTrue($weapon->update(1000.0));
        self::assertSame(2, $attempts);
        self::assertSame(2.0, $weapon->remainingSeconds());
    }

    public function test_two_weapons_can_share_a_definition_without_sharing_cooldown(): void
    {
        $definition = WeaponFixtures::definition();
        $execute = static fn (WeaponDefinition $definition): bool => true;
        $first = new Weapon($definition, $execute);
        $second = new Weapon($definition, $execute);
        self::assertTrue($first->update(0.0));
        self::assertTrue($second->isReady());
        self::assertTrue($second->update(0.0));
        self::assertFalse($first->update(0.5));
        self::assertSame(1.5, $first->remainingSeconds());
        self::assertSame(2.0, $second->remainingSeconds());
        self::assertTrue($second->update(2.0));
        self::assertSame(1.5, $first->remainingSeconds());
    }

    /** @return iterable<string, array{float}> */
    public static function invalidTimes(): iterable
    {
        yield 'negative' => [-1.0];
        yield 'inf' => [INF];
        yield 'negative inf' => [-INF];
        yield 'nan' => [NAN];
    }

    #[DataProvider('invalidTimes')]
    public function test_invalid_dt_does_not_attempt_or_change_cooldown(float $dt): void
    {
        $calls = 0;
        $weapon = new Weapon(WeaponFixtures::definition(), static function (WeaponDefinition $definition) use (&$calls): bool {
            $calls++;

            return true;
        });
        $weapon->update(0.0);
        try {
            $weapon->update($dt);
            self::fail('dt inválido precisa falhar.');
        } catch (InvalidArgumentException) {
            self::assertSame(2.0, $weapon->remainingSeconds());
            self::assertSame(1, $calls);
        }
    }

    public function test_executor_errors_propagate_without_consuming_cooldown_or_leaving_update_locked(): void
    {
        $failure = new RuntimeException('Falha da execução de teste.');
        $state = new class
        {
            public bool $fail = true;
        };
        $weapon = new Weapon(WeaponFixtures::definition(), static function (WeaponDefinition $definition) use ($failure, $state): bool {
            if ($state->fail) {
                throw $failure;
            }

            return true;
        });
        try {
            $weapon->update(0.0);
            self::fail('A falha deve chegar ao chamador.');
        } catch (RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
        self::assertTrue($weapon->isReady());
        $state->fail = false;
        self::assertTrue($weapon->update(0.0));
    }

    public function test_recursive_update_cannot_double_execute_the_same_weapon(): void
    {
        $weapon = null;
        $weapon = new Weapon(WeaponFixtures::definition(), static function (WeaponDefinition $definition) use (&$weapon): bool {
            self::assertInstanceOf(Weapon::class, $weapon);
            $weapon->update(0.0);

            return true;
        });
        $this->expectException(LogicException::class);
        $weapon->update(0.0);
    }
}
