<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Weapon;

use InvalidArgumentException;
use Jogo\Core\Config\WeaponConfigLoader;
use Jogo\Domain\Value\ModifierType;
use Jogo\Domain\Value\StatModifier;
use Jogo\Domain\Value\Stats;
use Jogo\Domain\Weapon\Weapon;
use Jogo\Domain\Weapon\WeaponCatalog;
use Jogo\Domain\Weapon\WeaponStatsMapper;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WeaponModifiersTest extends TestCase
{
    /** @return iterable<string, array{string, string, float}> */
    public static function attributes(): iterable
    {
        yield 'damage' => ['stone_sword', 'dano', 3.5];
        yield 'cooldown' => ['stone_sword', 'cooldown', 3.0];
        yield 'range' => ['stone_sword', 'alcance', 91.0];
        yield 'count' => ['bow', 'projeteis', 2.0];
        yield 'speed' => ['bow', 'velocidadeProjetil', 421.0];
        yield 'angle' => ['stone_sword', 'anguloGraus', 121.0];
        yield 'radius' => ['tnt', 'raioExplosao', 111.0];
        yield 'delay' => ['tnt', 'atrasoDetonacaoSegundos', 2.0];
    }

    #[DataProvider('attributes')]
    public function test_each_applicable_attribute_is_mapped_into_the_next_execution(string $id, string $attribute, float $expected): void
    {
        $base = self::catalog()->get($id);
        $received = [];
        $weapon = new Weapon($base, static function ($definition) use (&$received): bool {
            $received[] = $definition;

            return true;
        });
        $effect = new StatModifier($attribute, ModifierType::FLAT, 1.0, 'grant');
        $weapon->applyModifiers([$effect]);
        self::assertSame($base, $weapon->baseDefinition());
        self::assertSame($effect, $weapon->modifier('grant'));
        self::assertSame($expected, WeaponStatsMapper::fromDefinition($weapon->definition())->value($attribute));
        self::assertTrue($weapon->isReady());
        self::assertTrue($weapon->update(0.0));
        self::assertSame($weapon->definition(), $received[0]);
    }

    public function test_round_trip_preserves_all_optional_fields_and_identity(): void
    {
        foreach (self::catalog()->all() as $definition) {
            self::assertEquals($definition, WeaponStatsMapper::toDefinition($definition, WeaponStatsMapper::fromDefinition($definition)));
        }
        $this->expectException(InvalidArgumentException::class);
        WeaponStatsMapper::toDefinition(self::catalog()->get('torch'), new Stats(['dano' => 1.0]));
    }

    public function test_repeated_application_recalculates_base_and_removal_restores_base(): void
    {
        $weapon = new Weapon(self::catalog()->get('stone_sword'), static fn (): bool => true);
        $weapon->applyModifiers([new StatModifier('dano', ModifierType::FLAT, 1.0, 'flat')]);
        $weapon->applyModifiers([new StatModifier('dano', ModifierType::PERCENT, 1.0, 'percent')]);
        self::assertSame(7.0, $weapon->definition()->damage);
        self::assertTrue($weapon->removeModifier('flat'));
        self::assertSame(5.0, $weapon->definition()->damage);
        self::assertNull($weapon->modifier('flat'));
        self::assertFalse($weapon->removeModifier('missing'));
        self::assertTrue($weapon->removeModifier('percent'));
        self::assertSame(2.5, $weapon->definition()->damage);
        self::assertSame([], $weapon->modifiers());
    }

    /** @return iterable<string, array{string, string, float, ModifierType}> */
    public static function invalidChanges(): iterable
    {
        yield 'wrong shape' => ['stone_sword', 'raioExplosao', 1.0, ModifierType::FLAT];
        yield 'fraction' => ['bow', 'projeteis', 0.5, ModifierType::FLAT];
        yield 'percent fraction' => ['bow', 'projeteis', 0.5, ModifierType::PERCENT];
        yield 'count zero' => ['bow', 'projeteis', -1.0, ModifierType::FLAT];
        yield 'cooldown zero' => ['bow', 'cooldown', -1.0, ModifierType::PERCENT];
        yield 'angle too large' => ['stone_sword', 'anguloGraus', 300.0, ModifierType::FLAT];
        yield 'negative delay' => ['tnt', 'atrasoDetonacaoSegundos', -2.0, ModifierType::FLAT];
        yield 'negative damage' => ['torch', 'dano', -9.0, ModifierType::FLAT];
    }

    #[DataProvider('invalidChanges')]
    public function test_invalid_batch_is_atomic(string $id, string $attribute, float $value, ModifierType $type): void
    {
        $weapon = new Weapon(self::catalog()->get($id), static fn (): bool => true);
        $weapon->update(0.0);
        $weapon->update(0.1);
        $old = $weapon->definition();
        $remaining = $weapon->remainingSeconds();
        try {
            $weapon->applyModifiers([new StatModifier('dano', ModifierType::FLAT, 1.0, 'valid'),
                new StatModifier($attribute, $type, $value, 'invalid')]);
            self::fail('A alteração deveria falhar.');
        } catch (InvalidArgumentException) {
            self::assertSame($old, $weapon->definition());
            self::assertSame([], $weapon->modifiers());
            self::assertSame($remaining, $weapon->remainingSeconds());
        }
    }

    public function test_duplicate_source_does_not_change_existing_effect_or_cooldown(): void
    {
        $weapon = new Weapon(self::catalog()->get('torch'), static fn (): bool => true);
        $modifier = new StatModifier('dano', ModifierType::FLAT, 1.0, 'same');
        $weapon->applyModifiers([$modifier]);
        $old = $weapon->definition();
        try {
            $weapon->applyModifiers([$modifier]);
            self::fail('source duplicada deveria falhar.');
        } catch (InvalidArgumentException) {
            self::assertSame($old, $weapon->definition());
            self::assertSame([$modifier], $weapon->modifiers());
            self::assertTrue($weapon->isReady());
        }
    }

    public function test_removal_that_would_make_the_remaining_effect_invalid_is_atomic(): void
    {
        $weapon = new Weapon(self::catalog()->get('torch'), static fn (): bool => true);
        $weapon->applyModifiers([new StatModifier('dano', ModifierType::FLAT, 10.0, 'positive'),
            new StatModifier('dano', ModifierType::FLAT, -5.0, 'negative')]);
        $old = $weapon->definition();
        try {
            $weapon->removeModifier('positive');
            self::fail('Remoção inválida deveria falhar.');
        } catch (InvalidArgumentException) {
            self::assertSame($old, $weapon->definition());
            self::assertCount(2, $weapon->modifiers());
        }
    }

    public function test_modification_from_inside_execution_is_rejected(): void
    {
        $weapon = null;
        $weapon = new Weapon(self::catalog()->get('torch'), static function () use (&$weapon): bool {
            self::assertInstanceOf(Weapon::class, $weapon);
            $weapon->applyModifiers([new StatModifier('dano', ModifierType::FLAT, 1.0, 'unsafe')]);

            return true;
        });
        $this->expectException(LogicException::class);
        $weapon->update(0.0);
    }

    private static function catalog(): WeaponCatalog
    {
        return (new WeaponConfigLoader)->load(dirname(__DIR__, 4).'/config/weapons.json');
    }
}
