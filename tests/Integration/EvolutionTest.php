<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use InvalidArgumentException;
use Jogo\Core\Config\CharacterConfigLoader;
use Jogo\Core\Config\PassiveConfigLoader;
use Jogo\Domain\Character\Player;
use Jogo\Domain\Character\PlayerFactory;
use Jogo\Domain\Event\LevelUp;
use Jogo\Domain\Progression\EvolutionService;
use Jogo\Domain\Progression\LevelUp\LevelUpOfferGenerator;
use Jogo\Domain\Progression\LevelUp\LevelUpOption;
use Jogo\Domain\Progression\LevelUp\LevelUpOptionKind;
use Jogo\Domain\Progression\LevelUp\LevelUpService;
use Jogo\Domain\Progression\PassiveInventory;
use Jogo\Domain\Progression\ProgressionEffects;
use Jogo\Domain\Progression\WeaponLevels;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\Upgrade\UpgradeCatalog;
use Jogo\Domain\Weapon\Upgrade\WeaponProgressionService;
use Jogo\Domain\Weapon\Weapon;
use Jogo\Tests\Support\CombatHarness;
use Jogo\Tests\Support\ControlledRandomIndex;
use Jogo\Tests\Support\ProgressionFixtures;
use LogicException;
use PHPUnit\Framework\TestCase;

final class EvolutionTest extends TestCase
{
    private CombatHarness $harness;

    private Player $player;

    private WeaponProgressionService $progression;

    private ProgressionEffects $effects;

    private PassiveInventory $passives;

    private WeaponLevels $levels;

    private UpgradeCatalog $upgrades;

    private EvolutionService $evolutions;

    public function test_stone_sword_with_sharpness_becomes_enchanted_stone_sword(): void
    {
        $this->start();
        $this->upgradeTo('stone_sword', 5);
        $this->acquire('sharpness', 3);

        self::assertSame('enchanted_stone_sword', $this->evolutions->eligibleFor('stone_sword')?->definition->id);
        self::assertSame(0, $this->evolutions->evolve('enchanted_stone_sword'));

        $sword = $this->weapon(0);
        self::assertSame('enchanted_stone_sword', $sword->definition()->id);
        self::assertSame(9.0, $sword->baseDefinition()->damage);
        self::assertEqualsWithDelta(9.0 * 1.08 ** 3, $sword->definition()->damage, 1.0E-12);
        self::assertNull($sword->modifier('upgrades:stone_sword:2:0'));
        self::assertSame('enchanted_stone_sword', $this->levels->currentId('stone_sword'));
    }

    public function test_tnt_with_gunpowder_becomes_mega_tnt(): void
    {
        $this->start();
        $slot = $this->player->inventory()->add($this->harness->catalog->get('tnt'), $this->harness->combat);
        $this->levels->register('tnt');
        $this->upgradeTo('tnt', 5);
        $this->acquire('gunpowder', 3);

        self::assertSame($slot, $this->evolutions->evolve('mega_tnt'));

        $tnt = $this->weapon($slot);
        self::assertSame('mega_tnt', $tnt->definition()->id);
        self::assertEqualsWithDelta(220.0 * 1.1 ** 3, $tnt->definition()->explosionRadius, 1.0E-9);
        self::assertSame(18.0, $tnt->definition()->damage);
    }

    public function test_insufficient_requirements_change_nothing(): void
    {
        $this->start();
        $this->upgradeTo('stone_sword', 4);
        $this->acquire('sharpness', 3);
        $before = $this->weapon(0)->definition();
        $modifiers = $this->weapon(0)->modifiers();

        self::assertNull($this->evolutions->eligibleFor('stone_sword'));
        try {
            $this->evolutions->evolve('enchanted_stone_sword');
            self::fail('Arma no nível 4 não deveria evoluir.');
        } catch (InvalidArgumentException $error) {
            self::assertSame('enchanted_stone_sword: stone_sword precisa estar no nível 5.', $error->getMessage());
        }

        self::assertEquals($before, $this->weapon(0)->definition());
        self::assertSame($modifiers, $this->weapon(0)->modifiers());
        self::assertFalse($this->levels->isEvolved('stone_sword'));
    }

    public function test_passive_below_the_required_level_blocks_the_evolution(): void
    {
        $this->start();
        $this->upgradeTo('stone_sword', 5);
        $this->acquire('sharpness', 2);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('enchanted_stone_sword: sharpness precisa estar no nível 3.');
        $this->evolutions->evolve('enchanted_stone_sword');
    }

    public function test_missing_weapon_cannot_evolve(): void
    {
        $this->start();
        $this->acquire('gunpowder', 3);

        self::assertSame([], $this->evolutions->eligible());
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('mega_tnt: a arma tnt não está equipada.');
        $this->evolutions->evolve('mega_tnt');
    }

    public function test_a_weapon_evolves_only_once(): void
    {
        $this->start();
        $this->upgradeTo('stone_sword', 5);
        $this->acquire('sharpness', 3);
        $this->evolutions->evolve('enchanted_stone_sword');

        $this->expectException(InvalidArgumentException::class);
        $this->evolutions->evolve('enchanted_stone_sword');
    }

    public function test_level_up_offers_the_evolution_as_the_weapon_upgrade_and_applies_it(): void
    {
        $this->start();
        $this->upgradeTo('stone_sword', 5);
        $this->acquire('sharpness', 3);
        $generator = new LevelUpOfferGenerator($this->harness->catalog, $this->upgrades,
            (new PassiveConfigLoader)->load(dirname(__DIR__, 2).'/config/passives.json'), $this->passives,
            $this->levels, $this->player->inventory(), new ControlledRandomIndex(0), $this->evolutions);
        $service = new LevelUpService('player', $generator, $this->harness->catalog, $this->upgrades, $this->progression,
            $this->passives, $this->effects, $this->levels, $this->player->inventory(), $this->harness->combat, $this->evolutions);
        $service->subscribe($this->harness->events);
        $this->harness->events->publish(new LevelUp('player', 2, 0, 1.0));

        $keys = array_map(static fn (LevelUpOption $option): string => $option->key(), $service->currentOffer());
        self::assertContains('melhoria_arma:stone_sword:evolucao:enchanted_stone_sword', $keys);

        $service->choose((int) array_search('melhoria_arma:stone_sword:evolucao:enchanted_stone_sword', $keys, true));

        self::assertSame('enchanted_stone_sword', $this->weapon(0)->definition()->id);
        $this->harness->events->publish(new LevelUp('player', 3, 0, 2.0));
        $keys = array_map(static fn (LevelUpOption $option): string => $option->key(), $service->currentOffer());
        self::assertSame([], array_values(array_filter($keys, static fn (string $key): bool => str_contains($key, 'stone_sword'))));
    }

    public function test_only_weapon_upgrades_can_carry_an_evolution(): void
    {
        $this->start();
        $option = new LevelUpOption(LevelUpOptionKind::UPGRADE_WEAPON, 'stone_sword', 5,
            evolutionId: 'enchanted_stone_sword');

        self::assertSame('melhoria_arma:stone_sword:evolucao:enchanted_stone_sword', $option->key());
        $this->expectException(InvalidArgumentException::class);
        new LevelUpOption(LevelUpOptionKind::PASSIVE, 'sharpness', 1,
            evolutionId: 'enchanted_stone_sword');
    }

    public function test_all_sixteen_real_upgrades_raise_the_weapon_level_up_to_five(): void
    {
        $this->start();
        $promotions = 0;
        $this->upgradeTo('stone_sword', 5);
        $promotions += 4;
        $this->player->inventory()->add($this->harness->catalog->get('bow'), $this->harness->combat);
        $this->levels->register('bow');
        foreach (['bow', 'tnt', 'torch'] as $weaponId) {
            if ($weaponId !== 'bow') {
                $previous = $this->weapon(1)->definition()->id;
                $this->player->inventory()->replace(1, $this->harness->catalog->get($weaponId), $this->harness->combat);
                $this->levels->remove($previous);
                $this->levels->register($weaponId);
            }
            $this->upgradeTo($weaponId, 5);
            $promotions += 4;
            self::assertSame(5, $this->levels->level($weaponId));
            for ($level = 2; $level <= 5; $level++) {
                self::assertNotNull($this->weapon(1)->modifier("upgrades:{$weaponId}:{$level}:0"), "{$weaponId} nível {$level}");
            }
        }

        self::assertSame(16, $promotions);
        self::assertCount(16, $this->upgrades->all());
        self::assertSame(5, $this->levels->level('stone_sword'));
    }

    private function start(): void
    {
        $config = dirname(__DIR__, 2).'/config';
        $this->harness = new CombatHarness;
        $catalog = $this->harness->catalog;
        $characters = (new CharacterConfigLoader($catalog))->load($config.'/characters.json');
        $this->player = (new PlayerFactory($characters, $catalog))
            ->create('steve', 'player', Vector2::zero(), 6.0, $this->harness->combat);
        $this->progression = new WeaponProgressionService($this->player->inventory(),
            ProgressionFixtures::evolutions($catalog), $this->harness->combat);
        $this->effects = new ProgressionEffects($this->player, $this->progression);
        $this->passives = new PassiveInventory((new PassiveConfigLoader)->load($config.'/passives.json'), $this->effects);
        $this->levels = new WeaponLevels;
        $this->levels->register('stone_sword');
        $this->upgrades = ProgressionFixtures::upgrades($catalog);
        $this->evolutions = new EvolutionService(ProgressionFixtures::evolutions($catalog), $this->levels,
            $this->passives, $this->progression, $this->effects, $this->player->inventory());
    }

    private function upgradeTo(string $weaponId, int $level): void
    {
        foreach ($this->player->inventory()->weapons() as $slot => $weapon) {
            if ($weapon->definition()->id !== $weaponId) {
                continue;
            }
            for ($next = $this->levels->level($weaponId) + 1; $next <= $level; $next++) {
                $this->progression->applyUpgrade($slot, $this->upgrades->get($weaponId, $next));
                $this->levels->promote($weaponId, $next);
            }

            return;
        }
        throw new LogicException("{$weaponId} não equipada.");
    }

    private function acquire(string $passiveId, int $level): void
    {
        while ($this->passives->level($passiveId) < $level) {
            $this->passives->acquire($passiveId);
        }
    }

    private function weapon(int $slot): Weapon
    {
        return $this->player->inventory()->at($slot) ?? throw new LogicException("Slot {$slot} vazio.");
    }
}
