<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Value;

use Error;
use InvalidArgumentException;
use Jogo\Domain\Value\ModifierType;
use Jogo\Domain\Value\StatModifier;
use Jogo\Domain\Value\StatResolver;
use Jogo\Domain\Value\Stats;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class StatsTest extends TestCase
{
    public function test_stats_and_modifiers_are_immutable_and_values_are_copies(): void
    {
        $stats = new Stats(['dano' => 10.0]);
        $copy = $stats->values();
        $copy['dano'] = 99.0;
        self::assertSame(10.0, $stats->value('dano'));
        self::assertTrue($stats->has('dano'));
        self::assertFalse($stats->has('raioExplosao'));
        $modifier = new StatModifier('dano', ModifierType::FLAT, 2.0, 'grant');
        $this->expectException(Error::class);
        (new ReflectionProperty($modifier, 'value'))->setValue($modifier, 3.0);
    }

    /** @return iterable<string, array{string, float}> */
    public static function invalidValues(): iterable
    {
        yield 'blank' => ['', 1.0];
        yield 'unknown' => ['mana', 1.0];
        foreach (Stats::ATTRIBUTES as $attribute) {
            yield "$attribute nan" => [$attribute, NAN];
            yield "$attribute inf" => [$attribute, INF];
            yield "$attribute negative" => [$attribute, -1.0];
        }
        foreach (['cooldown', 'alcance', 'projeteis', 'velocidadeProjetil', 'anguloGraus', 'raioExplosao'] as $attribute) {
            yield "$attribute zero" => [$attribute, 0.0];
        }
        yield 'fractional count' => ['projeteis', 1.5];
        yield 'count overflow' => ['projeteis', (float) PHP_INT_MAX];
        yield 'angle overflow' => ['anguloGraus', 360.001];
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_stats_fail_without_clamping(string $attribute, float $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Stats([$attribute => $value]);
    }

    public function test_absent_value_is_an_error_and_empty_map_is_valid(): void
    {
        self::assertSame([], (new Stats([]))->values());
        $this->expectException(InvalidArgumentException::class);
        (new Stats(['dano' => 0.0]))->value('cooldown');
    }

    public function test_numbers_are_converted_to_float_but_other_types_are_rejected(): void
    {
        self::assertSame(['dano' => 2.0], (new Stats(['dano' => 2]))->values());
        foreach ([true, '2', null, []] as $invalid) {
            try {
                new Stats(['dano' => $invalid]);
                self::fail('Atributo não numérico deveria falhar.');
            } catch (InvalidArgumentException $error) {
                self::assertStringContainsString('dano', $error->getMessage());
            }
        }
    }

    /** @return iterable<string, array{string, float, string}> */
    public static function invalidModifiers(): iterable
    {
        yield 'unknown' => ['unknown', 1.0, 's'];
        yield 'blank attribute' => ['', 1.0, 's'];
        yield 'blank source' => ['dano', 1.0, ' '];
        yield 'nan' => ['dano', NAN, 's'];
        yield 'inf' => ['dano', INF, 's'];
    }

    #[DataProvider('invalidModifiers')]
    public function test_modifier_validates_canonical_attribute_value_and_source(string $attribute, float $value, string $source): void
    {
        $this->expectException(InvalidArgumentException::class);
        new StatModifier($attribute, ModifierType::FLAT, $value, $source);
    }

    /** @return iterable<string, array{list<array{string, float}>, float}> */
    public static function formulas(): iterable
    {
        yield 'flat' => [[['FLAT', 2.0]], 12.0];
        yield 'percent' => [[['PERCENT', 0.25]], 12.5];
        yield 'multiple flat' => [[['FLAT', 2.0], ['FLAT', 3.0]], 15.0];
        yield 'independent percent' => [[['PERCENT', 0.25], ['PERCENT', -0.10]], 11.25];
        yield 'flat before percents' => [[['PERCENT', 0.25], ['FLAT', 2.0], ['PERCENT', -0.10]], 13.5];
    }

    /** @param list<array{string, float}> $effects */
    #[DataProvider('formulas')]
    public function test_formula_and_recalculation_from_base(array $effects, float $expected): void
    {
        $base = new Stats(['dano' => 10.0]);
        $modifiers = [];
        foreach ($effects as $index => [$type, $value]) {
            $modifiers[] = new StatModifier('dano', ModifierType::from($type), $value, 's'.$index);
        }
        $resolver = new StatResolver;
        self::assertSame($expected, $resolver->resolve($base, $modifiers)->value('dano'));
        self::assertSame($expected, $resolver->resolve($base, array_reverse($modifiers))->value('dano'));
        self::assertSame($expected, $resolver->resolve($base, $modifiers)->value('dano'));
        self::assertSame(10.0, $base->value('dano'));
    }

    public function test_numerical_order_is_source_order_even_when_input_order_changes(): void
    {
        $modifiers = [new StatModifier('dano', ModifierType::FLAT, 1.0E16, 'a'),
            new StatModifier('dano', ModifierType::FLAT, -1.0E16, 'b'),
            new StatModifier('dano', ModifierType::FLAT, 1.0, 'c')];
        $resolver = new StatResolver;
        self::assertSame(11.0, $resolver->resolve(new Stats(['dano' => 10.0]), array_reverse($modifiers))->value('dano'));
    }

    public function test_targeted_missing_attribute_fails_but_global_resolution_ignores_it(): void
    {
        $base = new Stats(['dano' => 1.0]);
        $effects = [new StatModifier('raioExplosao', ModifierType::FLAT, 10.0, 'powder')];
        self::assertSame($base->values(), (new StatResolver)->resolve($base, $effects, ignoreMissing: true)->values());
        $this->expectException(InvalidArgumentException::class);
        (new StatResolver)->resolve($base, $effects);
    }

    public function test_duplicate_sources_are_rejected_even_for_different_attributes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new StatResolver)->resolve(new Stats(['dano' => 1.0, 'cooldown' => 2.0]),
            [new StatModifier('dano', ModifierType::FLAT, 1.0, 'same'), new StatModifier('cooldown', ModifierType::FLAT, 1.0, 'same')]);
    }

    public function test_overflowing_resolution_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new StatResolver)->resolve(new Stats(['dano' => PHP_FLOAT_MAX]),
            [new StatModifier('dano', ModifierType::PERCENT, 1.0, 'overflow')]);
    }
}
