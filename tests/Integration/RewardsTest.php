<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use InvalidArgumentException;
use Jogo\Core\Config\CharacterConfigLoader;
use Jogo\Core\Config\PassiveConfigLoader;
use Jogo\Core\Config\RewardConfigLoader;
use Jogo\Domain\Character\Player;
use Jogo\Domain\Character\PlayerFactory;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Event\ItemPicked;
use Jogo\Domain\Event\LevelUp;
use Jogo\Domain\Progression\EnemyXpTable;
use Jogo\Domain\Progression\EvolutionService;
use Jogo\Domain\Progression\Experience;
use Jogo\Domain\Progression\ExperienceSystem;
use Jogo\Domain\Progression\PassiveInventory;
use Jogo\Domain\Progression\ProgressionEffects;
use Jogo\Domain\Progression\Reward\ChestRewardService;
use Jogo\Domain\Progression\Reward\HealingItem;
use Jogo\Domain\Progression\Reward\PickupService;
use Jogo\Domain\Progression\Reward\TemporaryBonuses;
use Jogo\Domain\Progression\WeaponLevels;
use Jogo\Domain\Progression\XpRange;
use Jogo\Domain\Value\ModifierType;
use Jogo\Domain\Value\StatModifier;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\Upgrade\UpgradeCatalog;
use Jogo\Domain\Weapon\Upgrade\WeaponProgressionService;
use Jogo\Domain\Weapon\Weapon;
use Jogo\Tests\Support\CombatHarness;
use Jogo\Tests\Support\ControlledRandomIndex;
use Jogo\Tests\Support\ProgressionFixtures;
use LogicException;
use PHPUnit\Framework\TestCase;

final class RewardsTest extends TestCase
{
    private CombatHarness $harness;

    private Player $player;

    private WeaponProgressionService $progression;

    private ProgressionEffects $effects;

    private PassiveInventory $passives;

    private WeaponLevels $levels;

    private UpgradeCatalog $upgrades;

    private TemporaryBonuses $bonuses;

    private ExperienceSystem $experience;

    private PickupService $pickups;

    private HealingItem $steak;

    /** @var list<ItemPicked> */
    private array $picked = [];

    public function test_real_reward_configuration_is_read_without_inventing_values(): void
    {
        $this->start();

        self::assertSame(['steak', 'Steak', 5.0], [$this->steak->id, $this->steak->name, $this->steak->amount]);
        $catalog = (new RewardConfigLoader)->temporaryBonuses(self::config('scenery.json'));
        $furia = $catalog->get('furia');
        self::assertSame(15.0, $furia->durationSeconds);
        self::assertSame(['dano', ModifierType::PERCENT, 0.5], [$furia->modifiers[0]->attribute,
            $furia->modifiers[0]->type, $furia->modifiers[0]->value]);
        self::assertTrue($catalog->has('pressa'));
        self::assertTrue($catalog->has('agilidade'));
    }

    public function test_steak_heals_without_exceeding_max_health(): void
    {
        $this->start();
        $this->player->health()->damage(3.0);

        self::assertSame(3.0, $this->pickups->collectHealing('drop-1', $this->steak, 10.0));
        self::assertSame(20.0, $this->player->health()->current());
        self::assertSame(0.0, $this->pickups->collectHealing('drop-2', $this->steak, 11.0));
    }

    public function test_each_pickup_has_a_single_effect_and_a_single_event(): void
    {
        $this->start();
        $this->player->health()->damage(10.0);

        self::assertSame(5.0, $this->pickups->collectHealing('drop-1', $this->steak, 10.0));
        self::assertNull($this->pickups->collectHealing('drop-1', $this->steak, 10.0));

        self::assertSame(15.0, $this->player->health()->current());
        self::assertCount(1, $this->picked);
        self::assertSame(['player', 'drop-1', 'steak', 10.0], [$this->picked[0]->playerId, $this->picked[0]->pickupId,
            $this->picked[0]->itemId, $this->picked[0]->elapsedSeconds]);
    }

    public function test_dead_player_does_not_collect(): void
    {
        $this->start();
        $this->player->health()->damage(20.0);

        self::assertNull($this->pickups->collectHealing('drop-1', $this->steak, 10.0));
        self::assertFalse($this->pickups->wasCollected('drop-1'));
        self::assertSame([], $this->picked);
    }

    public function test_fury_lasts_fifteen_logical_seconds_and_keeps_other_effects_on_expiry(): void
    {
        $this->start();
        $this->passives->acquire('sharpness');
        $this->pickups->collectBonus('drop-1', 'furia', 0.0);

        self::assertEqualsWithDelta(2.5 * 1.08 * 1.5, $this->weapon(0)->definition()->damage, 1.0E-12);
        for ($step = 1; $step < 900; $step++) {
            self::assertSame([], $this->bonuses->update(1.0 / 60.0), "Expirou cedo no passo {$step}.");
        }
        self::assertSame(['furia'], $this->bonuses->update(1.0 / 60.0));

        self::assertFalse($this->bonuses->isActive('furia'));
        self::assertEqualsWithDelta(2.5 * 1.08, $this->weapon(0)->definition()->damage, 1.0E-12);
        self::assertNotNull($this->weapon(0)->modifier('passivo:sharpness:1:0'));
    }

    public function test_bonus_does_not_advance_while_the_game_is_paused(): void
    {
        $this->start();
        $this->pickups->collectBonus('drop-1', 'pressa', 0.0);
        $this->bonuses->update(10.0);

        self::assertEqualsWithDelta(5.0, $this->bonuses->remainingSeconds('pressa'), 1.0E-9);
        self::assertEqualsWithDelta(2.0 * 0.7, $this->weapon(0)->definition()->cooldownSeconds, 1.0E-12);

        self::assertSame(['pressa'], $this->bonuses->update(5.0));
        self::assertSame(2.0, $this->weapon(0)->definition()->cooldownSeconds);
    }

    public function test_collecting_an_active_bonus_renews_the_duration_without_stacking(): void
    {
        $this->start();
        $this->pickups->collectBonus('drop-1', 'furia', 0.0);
        $this->bonuses->update(10.0);

        $this->pickups->collectBonus('drop-2', 'furia', 10.0);

        self::assertSame(15.0, $this->bonuses->remainingSeconds('furia'));
        self::assertEqualsWithDelta(2.5 * 1.5, $this->weapon(0)->definition()->damage, 1.0E-12);
        self::assertCount(1, $this->effects->weaponEffects());
    }

    public function test_weapon_acquired_during_a_bonus_also_loses_it_on_expiry(): void
    {
        $this->start();
        $this->pickups->collectBonus('drop-1', 'furia', 0.0);
        $slot = $this->player->inventory()->add($this->harness->catalog->get('bow'), $this->harness->combat);
        $this->effects->syncWeapon($slot);
        self::assertEqualsWithDelta(3.2 * 1.5, $this->weapon($slot)->definition()->damage, 1.0E-12);

        $this->bonuses->update(15.0);

        self::assertSame(3.2, $this->weapon($slot)->definition()->damage);
        self::assertSame([], $this->effects->weaponEffects());
    }

    public function test_player_bonus_without_a_base_value_is_tracked_and_removed(): void
    {
        $this->start();
        $this->pickups->collectBonus('drop-1', 'agilidade', 0.0);
        self::assertNotNull($this->player->modifier('bonus:agilidade:1:0'));

        $this->bonuses->update(15.0);

        self::assertNull($this->player->modifier('bonus:agilidade:1:0'));
    }

    public function test_failed_bonus_keeps_the_pickup_available(): void
    {
        $this->start();

        try {
            $this->pickups->collectBonus('drop-1', 'invisibilidade', 1.0);
            self::fail('Bônus desconhecido deveria falhar.');
        } catch (InvalidArgumentException) {
        }

        self::assertFalse($this->pickups->wasCollected('drop-1'));
        self::assertSame([], $this->picked);
        self::assertTrue($this->pickups->collectBonus('drop-1', 'furia', 1.0));
        self::assertTrue($this->pickups->wasCollected('drop-1'));
    }

    public function test_invalid_xp_keeps_the_pickup_available_and_grants_nothing(): void
    {
        $this->start();

        try {
            $this->pickups->collectXp('drop-1', 'xp_pequeno', -5, 1.0);
            self::fail('XP negativo deveria falhar.');
        } catch (InvalidArgumentException) {
        }

        self::assertFalse($this->pickups->wasCollected('drop-1'));
        self::assertSame(0, $this->experience->experience()->totalXp());
        self::assertSame([], $this->pickups->collectXp('drop-1', 'xp_pequeno', 3, 1.0));
        self::assertSame(3, $this->experience->experience()->totalXp());
    }

    public function test_invalid_time_fails_before_healing_and_keeps_the_pickup(): void
    {
        $this->start();
        $this->player->health()->damage(10.0);

        try {
            $this->pickups->collectHealing('drop-1', $this->steak, -1.0);
            self::fail('Tempo negativo deveria falhar.');
        } catch (InvalidArgumentException) {
        }

        self::assertSame(10.0, $this->player->health()->current());
        self::assertFalse($this->pickups->wasCollected('drop-1'));
        self::assertSame(5.0, $this->pickups->collectHealing('drop-1', $this->steak, 2.0));
    }

    public function test_failure_while_applying_the_bonus_effect_keeps_the_pickup(): void
    {
        $this->start();
        $this->effects->grant([new StatModifier('dano', ModifierType::PERCENT, 0.5, 'bonus:furia:1:0')]);

        try {
            $this->pickups->collectBonus('drop-1', 'furia', 1.0);
            self::fail('Origem repetida deveria falhar durante a aplicação.');
        } catch (InvalidArgumentException $error) {
            self::assertStringContainsString('source duplicada', $error->getMessage());
        }

        self::assertFalse($this->pickups->wasCollected('drop-1'));
        self::assertFalse($this->bonuses->isActive('furia'));
    }

    public function test_unknown_bonus_is_rejected(): void
    {
        $this->start();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bônus desconhecido: invisibilidade.');
        $this->bonuses->activate('invisibilidade');
    }

    public function test_xp_pickup_grants_experience_and_can_level_up(): void
    {
        $this->start();
        $levels = [];
        $this->harness->events->subscribe(LevelUp::class, static function (LevelUp $event) use (&$levels): void {
            $levels[] = $event->level;
        });

        self::assertSame([2], $this->pickups->collectXp('drop-1', 'xp_pequeno', 9, 5.0));
        self::assertSame([2], $levels);
        self::assertNull($this->pickups->collectXp('drop-1', 'xp_pequeno', 9, 5.0));
    }

    public function test_xp_rain_doubles_the_xp_of_each_kill_while_active(): void
    {
        $this->start();

        $this->experience->setXpMultiplier(2.0);
        self::assertSame(6, $this->experience->onEnemyKilled(self::killed('e1')));
        $this->experience->setXpMultiplier(1.0);
        self::assertSame(3, $this->experience->onEnemyKilled(self::killed('e2')));
        self::assertSame(9, $this->experience->experience()->totalXp());
    }

    public function test_wooden_chest_grants_one_weapon_upgrade_once(): void
    {
        $this->start();
        $chests = $this->chests();

        self::assertSame(['stone_sword:2'], $chests->grantWeaponUpgrades('bau-1', 1));
        self::assertSame(2, $this->levels->level('stone_sword'));
        self::assertSame([], $chests->grantWeaponUpgrades('bau-1', 1));
        self::assertSame(2, $this->levels->level('stone_sword'));
    }

    public function test_chest_stops_when_no_upgrade_is_left(): void
    {
        $this->start();
        $chests = $this->chests();

        self::assertSame(['stone_sword:2', 'stone_sword:3', 'stone_sword:4'], $chests->grantWeaponUpgrades('bau-1', 3));
        self::assertSame(['stone_sword:5'], $chests->grantWeaponUpgrades('bau-2', 3));
    }

    public function test_chest_prefers_an_eligible_evolution(): void
    {
        $this->start();
        $chests = $this->chests();
        $chests->grantWeaponUpgrades('bau-1', 3);
        $chests->grantWeaponUpgrades('bau-2', 1);
        for ($level = 1; $level <= 3; $level++) {
            $this->passives->acquire('sharpness');
        }

        self::assertSame(['evolucao:enchanted_stone_sword'], $chests->grantWeaponUpgrades('bau-3', 2));
        self::assertSame('enchanted_stone_sword', $this->weapon(0)->definition()->id);
    }

    public function test_invalid_chest_quantity_is_rejected(): void
    {
        $this->start();

        $this->expectException(InvalidArgumentException::class);
        $this->chests()->grantWeaponUpgrades('bau-1', 0);
    }

    private function start(): void
    {
        $this->harness = new CombatHarness;
        $catalog = $this->harness->catalog;
        $characters = (new CharacterConfigLoader($catalog))->load(self::config('characters.json'));
        $this->player = (new PlayerFactory($characters, $catalog))
            ->create('steve', 'player', Vector2::zero(), 6.0, $this->harness->combat);
        $this->progression = new WeaponProgressionService($this->player->inventory(),
            ProgressionFixtures::evolutions($catalog), $this->harness->combat);
        $this->effects = new ProgressionEffects($this->player, $this->progression);
        $this->passives = new PassiveInventory((new PassiveConfigLoader)->load(self::config('passives.json')), $this->effects);
        $this->levels = new WeaponLevels;
        $this->levels->register('stone_sword');
        $this->upgrades = ProgressionFixtures::upgrades($catalog);
        $rewards = new RewardConfigLoader;
        $this->steak = $rewards->healingItem(self::config('chests.json'));
        $this->bonuses = new TemporaryBonuses($rewards->temporaryBonuses(self::config('scenery.json')), $this->effects);
        $this->experience = new ExperienceSystem('player', new Experience, new EnemyXpTable(['zombie' => new XpRange(3, 4)]),
            new ControlledRandomIndex(0), $this->harness->events);
        $this->pickups = new PickupService($this->player, $this->bonuses, $this->experience, $this->harness->events);
        $this->picked = [];
        $this->harness->events->subscribe(ItemPicked::class, function (ItemPicked $event): void {
            $this->picked[] = $event;
        });
    }

    private function chests(): ChestRewardService
    {
        $evolutions = new EvolutionService(ProgressionFixtures::evolutions($this->harness->catalog), $this->levels,
            $this->passives, $this->progression, $this->effects, $this->player->inventory());

        return new ChestRewardService($this->upgrades, $this->levels, $this->progression, $evolutions,
            $this->player->inventory(), new ControlledRandomIndex(0));
    }

    private function weapon(int $slot): Weapon
    {
        return $this->player->inventory()->at($slot) ?? throw new LogicException("Slot {$slot} vazio.");
    }

    private static function killed(string $id): EnemyKilled
    {
        return new EnemyKilled($id, 'zombie', false, Vector2::zero(), 'player', 'stone_sword', 1.0);
    }

    private static function config(string $file): string
    {
        return dirname(__DIR__, 2).'/config/'.$file;
    }
}
