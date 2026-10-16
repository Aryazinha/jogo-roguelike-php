<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use InvalidArgumentException;
use Jogo\Core\Config\CharacterConfigLoader;
use Jogo\Core\Config\PassiveConfigLoader;
use Jogo\Domain\Character\Player;
use Jogo\Domain\Character\PlayerFactory;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Progression\CharacterPassiveSystem;
use Jogo\Domain\Progression\EnemyXpTable;
use Jogo\Domain\Progression\Experience;
use Jogo\Domain\Progression\ExperienceSystem;
use Jogo\Domain\Progression\PassiveInventory;
use Jogo\Domain\Progression\ProgressionEffects;
use Jogo\Domain\Progression\XpRange;
use Jogo\Domain\Value\ModifierType;
use Jogo\Domain\Value\StatModifier;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\Upgrade\WeaponProgressionService;
use Jogo\Domain\Weapon\Weapon;
use Jogo\Tests\Support\CombatHarness;
use Jogo\Tests\Support\ControlledRandomIndex;
use Jogo\Tests\Support\ProgressionFixtures;
use LogicException;
use PHPUnit\Framework\TestCase;

final class PassiveProgressionTest extends TestCase
{
    private CombatHarness $harness;

    private Player $player;

    private WeaponProgressionService $service;

    private ProgressionEffects $effects;

    private PassiveInventory $passives;

    public function test_sharpness_raises_damage_from_the_base_with_one_source_per_level(): void
    {
        $this->start('steve');

        self::assertSame(1, $this->passives->acquire('sharpness'));
        self::assertSame(2, $this->passives->acquire('sharpness'));

        $sword = $this->weapon(0);
        self::assertEqualsWithDelta(2.5 * 1.08 * 1.08, $sword->definition()->damage, 1.0E-12);
        self::assertSame(2.5, $sword->baseDefinition()->damage);
        self::assertNotNull($sword->modifier('passivo:sharpness:1:0'));
        self::assertNotNull($sword->modifier('passivo:sharpness:2:0'));
        self::assertSame(['sharpness' => 2], $this->passives->owned());
    }

    public function test_max_level_cannot_be_exceeded_and_failure_changes_nothing(): void
    {
        $this->start('steve');
        for ($level = 1; $level <= 5; $level++) {
            $this->passives->acquire('clock');
        }
        $cooldown = $this->weapon(0)->definition()->cooldownSeconds;

        self::assertFalse($this->passives->canAcquire('clock'));
        try {
            $this->passives->acquire('clock');
            self::fail('O sexto nível deveria ser recusado.');
        } catch (InvalidArgumentException $error) {
            self::assertSame('clock já está no nível máximo 5.', $error->getMessage());
        }
        self::assertSame(5, $this->passives->level('clock'));
        self::assertSame($cooldown, $this->weapon(0)->definition()->cooldownSeconds);
    }

    public function test_golden_apple_goes_to_the_player_not_to_the_weapons(): void
    {
        $this->start('alex');

        $this->passives->acquire('golden_apple');

        self::assertSame(18.0, $this->player->health()->maximum());
        self::assertSame(18.0, $this->player->health()->current());
        self::assertSame([], $this->weapon(0)->modifiers());
    }

    public function test_weapon_effect_reaches_only_weapons_that_have_the_attribute_including_later_ones(): void
    {
        $this->start('steve');
        $this->passives->acquire('gunpowder');
        self::assertSame([], $this->weapon(0)->modifiers());

        $slot = $this->player->inventory()->add($this->harness->catalog->get('tnt'), $this->harness->combat);
        self::assertSame(['passivo:gunpowder:1:0'], $this->effects->syncWeapon($slot));

        self::assertEqualsWithDelta(110.0 * 1.1, $this->weapon($slot)->definition()->explosionRadius, 1.0E-12);
        self::assertSame([], $this->effects->syncWeapon($slot));
    }

    public function test_effects_return_after_an_evolution_discards_modifiers(): void
    {
        $this->start('steve');
        $this->passives->acquire('sharpness');

        $this->service->evolve(0, 'enchanted_stone_sword');
        self::assertSame(9.0, $this->weapon(0)->definition()->damage);

        $this->effects->syncWeapon(0);
        self::assertEqualsWithDelta(9.0 * 1.08, $this->weapon(0)->definition()->damage, 1.0E-12);
    }

    public function test_effects_on_missing_player_bases_are_tracked_without_inventing_values(): void
    {
        $this->start('steve');

        $this->passives->acquire('feather');

        self::assertNotNull($this->player->modifier('passivo:feather:1:0'));
        self::assertFalse($this->player->stats()->has('velocidadeJogador'));
    }

    public function test_steve_receives_the_damage_enchantment_every_five_levels(): void
    {
        $this->start('steve');
        $system = new CharacterPassiveSystem($this->player, $this->effects);

        for ($level = 2; $level <= 10; $level++) {
            $system->onLevel($level);
        }
        self::assertSame([5, 10], $system->pendingLevels());
        self::assertSame(2.5, $this->weapon(0)->definition()->damage);

        self::assertSame([5, 10], $system->applyPending());

        self::assertSame([5, 10], $system->grantedLevels());
        self::assertEqualsWithDelta(2.5 * 1.1 * 1.1, $this->weapon(0)->definition()->damage, 1.0E-12);
        self::assertFalse($system->onLevel(10));
    }

    public function test_alex_cooldown_passive_is_granted_on_every_fourth_level_from_a_big_xp_gain(): void
    {
        $this->start('alex');
        $passive = new CharacterPassiveSystem($this->player, $this->effects);
        $passive->subscribe($this->harness->events);
        $xp = new ExperienceSystem($this->player->id(), new Experience,
            new EnemyXpTable(['warden' => new XpRange(150, 150)]), new ControlledRandomIndex(0), $this->harness->events);
        $xp->subscribe();

        $this->harness->events->publish(new EnemyKilled('boss-1', 'warden', true, Vector2::zero(), 'player', 'bow', 300.0));
        $passive->applyPending();

        self::assertSame(9, $xp->experience()->level());
        self::assertSame([4, 8], $passive->grantedLevels());
        self::assertEqualsWithDelta(1.8 - 0.2 - 0.2, $this->weapon(0)->definition()->cooldownSeconds, 1.0E-12);
    }

    public function test_cooldown_that_would_reach_zero_is_skipped_for_that_weapon_only(): void
    {
        $this->start('alex');
        $system = new CharacterPassiveSystem($this->player, $this->effects);

        for ($level = 4; $level <= 40; $level += 4) {
            self::assertTrue($system->onLevel($level));
        }
        $system->applyPending();

        $bow = $this->weapon(0);
        self::assertCount(10, $this->effects->weaponEffects());
        self::assertCount(8, $bow->modifiers());
        self::assertEqualsWithDelta(0.2, $bow->definition()->cooldownSeconds, 1.0E-12);
    }

    public function test_repeated_source_is_rejected(): void
    {
        $this->start('steve');
        $modifier = new StatModifier('dano', ModifierType::PERCENT, 0.1, 'teste:repetido');
        $this->effects->grant([$modifier]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('source duplicada: teste:repetido.');
        $this->effects->grant([$modifier]);
    }

    public function test_unknown_passive_is_rejected(): void
    {
        $this->start('steve');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Passivo desconhecido: totem.');
        $this->passives->acquire('totem');
    }

    private function start(string $characterId): void
    {
        $config = dirname(__DIR__, 2).'/config';
        $this->harness = new CombatHarness;
        $characters = (new CharacterConfigLoader($this->harness->catalog))->load($config.'/characters.json');
        $this->player = (new PlayerFactory($characters, $this->harness->catalog))
            ->create($characterId, 'player', Vector2::zero(), 6.0, $this->harness->combat);
        $this->service = new WeaponProgressionService($this->player->inventory(),
            ProgressionFixtures::evolutions($this->harness->catalog), $this->harness->combat);
        $this->effects = new ProgressionEffects($this->player, $this->service);
        $this->passives = new PassiveInventory((new PassiveConfigLoader)->load($config.'/passives.json'), $this->effects);
    }

    private function weapon(int $slot): Weapon
    {
        return $this->player->inventory()->at($slot) ?? throw new LogicException("Slot {$slot} vazio.");
    }
}
