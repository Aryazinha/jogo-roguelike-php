<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use InvalidArgumentException;
use Jogo\Core\Config\CharacterConfigLoader;
use Jogo\Core\Config\PassiveConfigLoader;
use Jogo\Domain\Character\Player;
use Jogo\Domain\Character\PlayerFactory;
use Jogo\Domain\Event\LevelUp;
use Jogo\Domain\Progression\LevelUp\LevelUpOfferGenerator;
use Jogo\Domain\Progression\LevelUp\LevelUpOption;
use Jogo\Domain\Progression\LevelUp\LevelUpOptionKind;
use Jogo\Domain\Progression\LevelUp\LevelUpService;
use Jogo\Domain\Progression\ModifierTemplate;
use Jogo\Domain\Progression\PassiveCatalog;
use Jogo\Domain\Progression\PassiveDefinition;
use Jogo\Domain\Progression\PassiveInventory;
use Jogo\Domain\Progression\ProgressionEffects;
use Jogo\Domain\Progression\WeaponLevels;
use Jogo\Domain\Random\RandomIndex;
use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Domain\Value\ModifierType;
use Jogo\Domain\Value\StatModifier;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\Upgrade\UpgradeCatalog;
use Jogo\Domain\Weapon\Upgrade\UpgradeDefinition;
use Jogo\Domain\Weapon\Upgrade\WeaponProgressionService;
use Jogo\Domain\Weapon\WeaponCatalog;
use Jogo\Tests\Support\CombatHarness;
use Jogo\Tests\Support\ControlledRandomIndex;
use Jogo\Tests\Support\ProgressionFixtures;
use LogicException;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;

final class LevelUpChoiceTest extends TestCase
{
    private CombatHarness $harness;

    private Player $player;

    private PassiveInventory $passives;

    private WeaponLevels $levels;

    private LevelUpService $service;

    public function test_offer_has_one_option_of_each_kind_of_rf14(): void
    {
        $this->start('steve');
        $this->levelUp(2);

        self::assertSame(['nova_arma:bow:1', 'melhoria_arma:stone_sword:2', 'item_passivo:sharpness:1'],
            self::keys($this->service->currentOffer()));
        self::assertSame($this->service->currentOffer(), $this->service->currentOffer());
    }

    public function test_choices_are_only_requested_after_a_level_up(): void
    {
        $this->start('steve');
        self::assertFalse($this->service->hasPendingChoice());

        $this->expectException(LogicException::class);
        $this->service->currentOffer();
    }

    public function test_upgrade_choice_is_applied_once_and_raises_the_weapon_level(): void
    {
        $this->start('steve');
        $this->levelUp(2);
        $this->service->currentOffer();

        $chosen = $this->service->choose(1);

        self::assertSame(LevelUpOptionKind::UPGRADE_WEAPON, $chosen->kind);
        self::assertSame(2, $this->levels->level('stone_sword'));
        self::assertNotNull($this->player->inventory()->at(0)?->modifier('upgrades:stone_sword:2:0'));
        self::assertFalse($this->service->hasPendingChoice());

        $this->expectException(LogicException::class);
        $this->service->choose(1);
    }

    public function test_new_weapon_goes_to_the_free_slot_and_receives_existing_passive_effects(): void
    {
        $this->start('steve');
        $this->passives->acquire('sharpness');
        $this->levelUp(2);
        $this->service->currentOffer();

        $this->service->choose(0);

        $bow = $this->player->inventory()->at(1);
        self::assertSame('bow', $bow?->definition()->id);
        self::assertEqualsWithDelta(3.2 * 1.08, $bow->definition()->damage, 1.0E-12);
        self::assertSame(1, $this->levels->level('bow'));
    }

    public function test_full_inventory_requires_choosing_which_weapon_to_replace(): void
    {
        $this->start('steve');
        $this->levelUp(2);
        $this->service->currentOffer();
        $this->service->choose(0);
        $this->levelUp(3);

        $offer = $this->service->currentOffer();
        self::assertSame('nova_arma:tnt:1', $offer[0]->key());
        self::assertTrue($offer[0]->requiresReplacement);

        try {
            $this->service->choose(0);
            self::fail('Sem slot deveria falhar.');
        } catch (InvalidArgumentException $error) {
            self::assertSame('Inventário cheio: informe o slot da arma a substituir.', $error->getMessage());
        }
        self::assertTrue($this->service->hasPendingChoice());
        self::assertSame('bow', $this->weaponId(1));

        $this->service->choose(0, 1);

        self::assertSame('tnt', $this->weaponId(1));
        self::assertFalse($this->levels->has('bow'));
        self::assertTrue($this->levels->has('tnt'));
    }

    public function test_replacement_slot_is_rejected_for_options_that_do_not_replace(): void
    {
        $this->start('steve');
        $this->levelUp(2);
        $this->service->currentOffer();

        try {
            $this->service->choose(2, 0);
            self::fail('Passivo não substitui arma.');
        } catch (InvalidArgumentException) {
        }

        self::assertSame(0, $this->passives->level('sharpness'));
        self::assertTrue($this->service->hasPendingChoice());
    }

    public function test_unknown_option_index_is_rejected(): void
    {
        $this->start('steve');
        $this->levelUp(2);
        $this->service->currentOffer();

        $this->expectException(OutOfBoundsException::class);
        $this->service->choose(5);
    }

    public function test_weapon_at_its_last_upgrade_is_no_longer_offered(): void
    {
        $this->start('steve');
        for ($level = 2; $level <= 5; $level++) {
            $this->levelUp($level);
            $this->service->currentOffer();
            $this->service->choose(1);
        }
        self::assertSame(5, $this->levels->level('stone_sword'));

        $this->levelUp(6);
        $kinds = array_map(static fn (LevelUpOption $o): LevelUpOptionKind => $o->kind, $this->service->currentOffer());

        self::assertNotContains(LevelUpOptionKind::UPGRADE_WEAPON, $kinds);
    }

    public function test_each_level_up_creates_its_own_pending_choice(): void
    {
        $this->start('steve');
        $this->levelUp(2);
        $this->levelUp(3);

        self::assertSame(2, $this->service->pendingLevels());
        $this->service->currentOffer();
        $this->service->choose(2);
        self::assertSame(1, $this->service->pendingLevels());
    }

    public function test_level_up_of_another_player_is_ignored(): void
    {
        $this->start('steve');
        $this->harness->events->publish(new LevelUp('outro', 2, 9, 1.0));

        self::assertFalse($this->service->hasPendingChoice());
    }

    public function test_same_seed_produces_the_same_offers(): void
    {
        $run = function (): array {
            $this->start('alex', new SeededRandomIndex(77));
            $keys = [];
            for ($level = 2; $level <= 8; $level++) {
                $this->levelUp($level);
                $offer = $this->service->currentOffer();
                $keys[] = self::keys($offer);
                $this->service->choose(count($offer) - 1, $offer[count($offer) - 1]->requiresReplacement ? 1 : null);
            }

            return $keys;
        };

        self::assertSame($run(), $run());
    }

    public function test_without_eligible_options_the_level_is_consumed_without_a_choice(): void
    {
        $this->start('steve');
        $weapons = new WeaponCatalog([$this->harness->catalog->get('stone_sword')]);
        $upgrades = new UpgradeCatalog([new UpgradeDefinition('stone_sword', 2, 'Melhoria',
            [new StatModifier('dano', ModifierType::FLAT, 1.0, 'upgrades:stone_sword:2:0')])]);
        $passiveCatalog = new PassiveCatalog([new PassiveDefinition('unico', 'Único', 'Teste', 1,
            [new ModifierTemplate('dano', ModifierType::PERCENT, 0.1)])]);
        $effects = new ProgressionEffects($this->player, $this->progression());
        $passives = new PassiveInventory($passiveCatalog, $effects);
        $levels = new WeaponLevels;
        $service = new LevelUpService('player', new LevelUpOfferGenerator($weapons, $upgrades, $passiveCatalog, $passives,
            $levels, $this->player->inventory(), new ControlledRandomIndex(0)), $weapons, $upgrades, $this->progression(),
            $passives, $effects, $levels, $this->player->inventory(), $this->harness->combat);
        $service->subscribe($this->harness->events);

        $this->levelUp(2);
        self::assertCount(2, $service->currentOffer());
        $service->choose(0);
        $this->levelUp(3);
        self::assertCount(1, $service->currentOffer());
        $service->choose(0);

        $this->levelUp(4);
        self::assertSame([], $service->currentOffer());
        self::assertFalse($service->hasPendingChoice());
    }

    private function start(string $characterId, ?RandomIndex $random = null): void
    {
        $config = dirname(__DIR__, 2).'/config';
        $this->harness = new CombatHarness;
        $catalog = $this->harness->catalog;
        $characters = (new CharacterConfigLoader($catalog))->load($config.'/characters.json');
        $this->player = (new PlayerFactory($characters, $catalog))
            ->create($characterId, 'player', Vector2::zero(), 6.0, $this->harness->combat);
        $progression = $this->progression();
        $effects = new ProgressionEffects($this->player, $progression);
        $passiveCatalog = (new PassiveConfigLoader)->load($config.'/passives.json');
        $this->passives = new PassiveInventory($passiveCatalog, $effects);
        $this->levels = new WeaponLevels;
        $upgrades = ProgressionFixtures::upgrades($catalog);
        $generator = new LevelUpOfferGenerator($catalog, $upgrades, $passiveCatalog, $this->passives, $this->levels,
            $this->player->inventory(), $random ?? new ControlledRandomIndex(0));
        $this->service = new LevelUpService('player', $generator, $catalog, $upgrades, $progression, $this->passives,
            $effects, $this->levels, $this->player->inventory(), $this->harness->combat);
        $this->service->subscribe($this->harness->events);
    }

    private function progression(): WeaponProgressionService
    {
        return new WeaponProgressionService($this->player->inventory(),
            ProgressionFixtures::evolutions($this->harness->catalog), $this->harness->combat);
    }

    private function weaponId(int $slot): ?string
    {
        return $this->player->inventory()->at($slot)?->definition()->id;
    }

    private function levelUp(int $level): void
    {
        $this->harness->events->publish(new LevelUp('player', $level, 0, 1.0));
    }

    /**
     * @param  list<LevelUpOption>  $offer
     * @return list<string>
     */
    private static function keys(array $offer): array
    {
        return array_map(static fn (LevelUpOption $option): string => $option->key(), $offer);
    }
}
