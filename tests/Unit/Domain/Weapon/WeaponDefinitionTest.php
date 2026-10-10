<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Weapon;

use Error;
use InvalidArgumentException;
use Jogo\Domain\Weapon\WeaponDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class WeaponDefinitionTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function shapes(): iterable
    {
        foreach (array_keys(WeaponDefinition::TARGETING_BY_SHAPE) as $shape) {
            yield $shape => [$shape];
        }
    }

    #[DataProvider('shapes')]
    public function test_each_format_preserves_applicable_fields_and_has_no_fictitious_values(string $shape): void
    {
        $definition = self::definition($shape);
        self::assertSame('test', $definition->id);
        self::assertSame('Test weapon', $definition->name);
        self::assertSame(2.5, $definition->damage);
        self::assertSame(2.0, $definition->cooldownSeconds);
        self::assertSame(70.0, $definition->range);
        self::assertSame($shape, $definition->shapeId);
        self::assertSame(WeaponDefinition::TARGETING_BY_SHAPE[$shape], $definition->targetingId);
        $payload = get_object_vars($definition);
        foreach (['angleDegrees', 'projectileCount', 'projectileSpeed', 'explosionRadius', 'detonationDelaySeconds'] as $field) {
            $expected = self::arguments($shape)[$field] ?? null;
            self::assertSame($expected, $payload[$field]);
        }
    }

    public function test_definition_is_final_readonly_and_cannot_be_changed(): void
    {
        $reflection = new ReflectionClass(WeaponDefinition::class);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        $definition = self::definition('pulso');
        $this->expectException(Error::class);
        (new ReflectionProperty($definition, 'damage'))->setValue($definition, 99.0);
    }

    /** @return iterable<string, array{string, string, float|int|string|null}> */
    public static function invalidFields(): iterable
    {
        foreach (['id', 'name', 'targetingId', 'shapeId'] as $field) {
            yield "$field:empty" => ['pulso', $field, ''];
            yield "$field:blank" => ['pulso', $field, " \t\n"];
        }
        $numeric = ['damage' => 'pulso', 'cooldownSeconds' => 'pulso', 'range' => 'pulso',
            'angleDegrees' => 'meia_lua', 'projectileSpeed' => 'projetil',
            'explosionRadius' => 'area', 'detonationDelaySeconds' => 'area'];
        foreach ($numeric as $field => $shape) {
            foreach (['negative' => -1.0, 'inf' => INF, 'negative inf' => -INF, 'nan' => NAN] as $label => $value) {
                yield "$field:$label" => [$shape, $field, $value];
            }
            if (! in_array($field, ['damage', 'detonationDelaySeconds'], true)) {
                yield "$field:zero" => [$shape, $field, 0.0];
            }
        }
        yield 'angle over 360' => ['meia_lua', 'angleDegrees', 360.1];
        yield 'zero projectiles' => ['projetil', 'projectileCount', 0];
        yield 'negative projectiles' => ['projetil', 'projectileCount', -1];
        yield 'unknown targeting' => ['pulso', 'targetingId', 'unknown'];
        yield 'unknown shape' => ['pulso', 'shapeId', 'unknown'];
        foreach (['angleDegrees' => 'meia_lua', 'projectileCount' => 'projetil', 'projectileSpeed' => 'projetil',
            'explosionRadius' => 'area', 'detonationDelaySeconds' => 'area'] as $field => $shape) {
            yield "$field:missing" => [$shape, $field, null];
            yield "$field:wrong format" => ['pulso', $field, $field === 'projectileCount' ? 1 : 1.0];
        }
        foreach (['meia_lua', 'projetil', 'area'] as $shape) {
            yield "$shape:incompatible targeting" => [$shape, 'targetingId', 'area_ao_redor'];
        }
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_fields_fail_with_the_field_name(string $shape, string $field, float|int|string|null $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($field);
        self::definition($shape, [$field => $value]);
    }

    public function test_valid_boundaries_and_fractional_continuous_values_are_preserved(): void
    {
        $arc = self::definition('meia_lua', ['damage' => 0.0, 'angleDegrees' => 360.0, 'id' => ' 0 ']);
        self::assertSame(0.0, $arc->damage);
        self::assertSame(360.0, $arc->angleDegrees);
        self::assertSame(' 0 ', $arc->id);
        $area = self::definition('area', ['detonationDelaySeconds' => 0.0, 'explosionRadius' => 0.5]);
        self::assertSame(0.0, $area->detonationDelaySeconds);
        self::assertSame(0.5, $area->explosionRadius);
    }

    /** @param array<string, mixed> $changes */
    private static function definition(string $shape, array $changes = []): WeaponDefinition
    {
        return (new ReflectionClass(WeaponDefinition::class))->newInstanceArgs(array_replace(self::arguments($shape), $changes));
    }

    /** @return array<string, string|float|int> */
    private static function arguments(string $shape): array
    {
        $specific = match ($shape) {
            'meia_lua' => ['angleDegrees' => 120.0],
            'projetil' => ['projectileCount' => 1, 'projectileSpeed' => 420.0],
            'area' => ['explosionRadius' => 110.0, 'detonationDelaySeconds' => 1.0],
            default => [],
        };

        return ['id' => 'test', 'name' => 'Test weapon', 'damage' => 2.5, 'cooldownSeconds' => 2.0,
            'targetingId' => WeaponDefinition::TARGETING_BY_SHAPE[$shape], 'shapeId' => $shape, 'range' => 70.0] + $specific;
    }
}
