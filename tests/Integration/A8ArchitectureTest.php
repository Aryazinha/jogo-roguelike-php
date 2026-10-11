<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Domain\Value\ModifierType;
use Jogo\Domain\Value\StatModifier;
use Jogo\Domain\Value\Stats;
use Jogo\Domain\Weapon\ResolvedWeaponStats;
use Jogo\Domain\Weapon\Upgrade\EvolutionCatalog;
use Jogo\Domain\Weapon\Upgrade\EvolutionDefinition;
use Jogo\Domain\Weapon\Upgrade\UpgradeCatalog;
use Jogo\Domain\Weapon\Upgrade\UpgradeDefinition;
use Jogo\Domain\Weapon\Upgrade\WeaponProgressionService;
use Jogo\Tests\Support\CombatHarness;
use Jogo\Tests\Support\ProgressionFixtures;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class A8ArchitectureTest extends TestCase
{
    public function test_ratified_value_objects_and_configuration_definitions_are_immutable(): void
    {
        foreach ([Stats::class, StatModifier::class, ResolvedWeaponStats::class, UpgradeDefinition::class,
            EvolutionDefinition::class, UpgradeCatalog::class, EvolutionCatalog::class] as $class) {
            $reflection = new ReflectionClass($class);
            self::assertTrue($reflection->isFinal());
            self::assertTrue($reflection->isReadOnly());
        }
        self::assertSame(['FLAT', 'PERCENT'], array_column(ModifierType::cases(), 'value'));
        $constructor = (new ReflectionClass(StatModifier::class))->getConstructor();
        self::assertNotNull($constructor);
        self::assertSame(['attribute', 'type', 'value', 'source'], array_map(static fn ($p): string => $p->getName(), $constructor->getParameters()));
    }

    public function test_command_api_has_no_eligibility_or_level_state_and_reuses_the_executor(): void
    {
        $reflection = new ReflectionClass(WeaponProgressionService::class);
        $methods = array_map(static fn (ReflectionMethod $method): string => $method->getName(), $reflection->getMethods(ReflectionMethod::IS_PUBLIC));
        self::assertSame(['__construct', 'apply', 'applyUpgrade', 'remove', 'evolve'], $methods);
        $h = new CombatHarness;
        $h->world->add('target', 20.0);
        $h->equip('stone_sword');
        // Nenhum personagem, nível ou passivo adquirido é fornecido ao serviço da A.
        ProgressionFixtures::service($h)->evolve(0, 'enchanted_stone_sword');
        self::assertSame(0.0, $h->inventory->at(0)?->remainingSeconds());
        self::assertCount(0, $h->hits);
        $h->step(0.0, 0.0);
        self::assertSame('enchanted_stone_sword', $h->hits[0]->weaponId);
        self::assertSame(9.0, $h->hits[0]->appliedDamage);
    }
}
