<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Domain\Ai\EntityView;
use Jogo\Domain\Ai\WorldView;
use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Domain\System\WeaponAttackSystem;
use Jogo\Domain\Weapon\EnemyTargetResolver;
use Jogo\Domain\Weapon\Targeting\TargetingStrategy;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

final class A7ArchitectureTest extends TestCase
{
    public function test_targeting_has_only_the_ratified_read_contract_and_snapshots_have_no_mutable_entity_references(): void
    {
        $world = new ReflectionClass(WorldView::class);
        self::assertSame(['playerPosition', 'neighbors'], array_map(static fn ($method): string => $method->getName(), $world->getMethods()));
        $strategy = (new ReflectionClass(TargetingStrategy::class))->getMethod('select');
        $parameters = $strategy->getParameters();
        self::assertSame('context', $parameters[0]->getName());
        $worldType = $parameters[1]->getType();
        self::assertInstanceOf(ReflectionNamedType::class, $worldType);
        self::assertSame(WorldView::class, $worldType->getName());
        $snapshot = new ReflectionClass(EntityView::class);
        self::assertTrue($snapshot->isFinal());
        self::assertTrue($snapshot->isReadOnly());
        self::assertSame([], $snapshot->getInterfaceNames());
        foreach ($snapshot->getProperties() as $property) {
            $type = $property->getType();
            self::assertInstanceOf(ReflectionNamedType::class, $type);
            self::assertContains($type->getName(), ['string', 'float', 'bool', 'Jogo\\Domain\\Value\\Vector2']);
        }
    }

    public function test_executor_uses_ports_and_requires_externally_seeded_randomness(): void
    {
        $executor = new ReflectionClass(WeaponAttackSystem::class);
        $constructor = $executor->getConstructor();
        self::assertNotNull($constructor);
        $types = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            self::assertInstanceOf(ReflectionNamedType::class, $type);
            $name = $type->getName();
            $types[] = $name;
            self::assertFalse(str_starts_with($name, 'Jogo\\Infrastructure\\'));
            self::assertFalse(str_starts_with($name, 'Jogo\\Application\\'));
        }
        self::assertContains(WorldView::class, $types);
        self::assertContains(EnemyTargetResolver::class, $types);
        $rng = (new ReflectionClass(SeededRandomIndex::class))->getConstructor();
        self::assertNotNull($rng);
        self::assertSame(1, $rng->getNumberOfRequiredParameters());
    }
}
