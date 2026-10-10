<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Value;

use InvalidArgumentException;
use Jogo\Domain\Value\Damage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DamageTest extends TestCase
{
    public function test_damage_is_final_and_readonly(): void
    {
        $reflection = new ReflectionClass(Damage::class);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
    }

    public function test_it_preserves_resolved_source_weapon_and_fractional_amount(): void
    {
        $damage = new Damage(2.5, 'attacker-instance', 'evolved-weapon');

        self::assertSame(2.5, $damage->amount);
        self::assertSame('attacker-instance', $damage->sourceEntityId);
        self::assertSame('evolved-weapon', $damage->weaponId);
    }

    public function test_zero_and_unknown_origin_are_valid(): void
    {
        $damage = new Damage(0.0);

        self::assertSame(0.0, $damage->amount);
        self::assertNull($damage->sourceEntityId);
        self::assertNull($damage->weaponId);
    }

    /** @return iterable<string, array{float}> */
    public static function invalidAmounts(): iterable
    {
        yield 'negative' => [-1.0];
        yield 'infinity' => [INF];
        yield 'negative infinity' => [-INF];
        yield 'nan' => [NAN];
    }

    #[DataProvider('invalidAmounts')]
    public function test_it_rejects_invalid_amounts(float $amount): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Damage($amount);
    }

    /** @return iterable<string, array{?string, ?string}> */
    public static function invalidIds(): iterable
    {
        yield 'empty source' => ['', null];
        yield 'blank source' => [" \t\n", null];
        yield 'empty weapon' => [null, ''];
        yield 'blank weapon' => [null, " \t\n"];
    }

    #[DataProvider('invalidIds')]
    public function test_it_rejects_present_but_blank_ids(?string $source, ?string $weapon): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Damage(1.0, $source, $weapon);
    }

    public function test_it_does_not_transform_valid_ids_or_cap_finite_damage(): void
    {
        $damage = new Damage(PHP_FLOAT_MAX, ' 0 ', '10');

        self::assertSame(PHP_FLOAT_MAX, $damage->amount);
        self::assertSame(' 0 ', $damage->sourceEntityId);
        self::assertSame('10', $damage->weaponId);
    }
}
