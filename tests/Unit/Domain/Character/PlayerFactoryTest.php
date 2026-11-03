<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Character;

use InvalidArgumentException;
use Jogo\Core\Config\CharacterConfigLoader;
use Jogo\Core\Config\WeaponConfigLoader;
use Jogo\Domain\Character\CharacterCatalog;
use Jogo\Domain\Character\CharacterDefinition;
use Jogo\Domain\Character\CharacterPassive;
use Jogo\Domain\Character\Player;
use Jogo\Domain\Character\PlayerFactory;
use Jogo\Domain\Value\ModifierType;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\CombatInventory;
use Jogo\Domain\Weapon\WeaponCatalog;
use Jogo\Tests\Support\WeaponFixtures;
use PHPUnit\Framework\TestCase;

final class PlayerFactoryTest extends TestCase
{
    public function test_steve_starts_with_20_hp_stone_sword_and_damage_passive(): void
    {
        $player = self::factory()->create('steve', 'player-1', new Vector2(10.0, 20.0), 6.0, self::idle(...));

        self::assertSame('player-1', $player->id());
        self::assertTrue($player->position()->equals(new Vector2(10.0, 20.0)));
        self::assertSame(6.0, $player->collisionRadius());
        self::assertSame(20.0, $player->health()->current());
        self::assertSame(20.0, $player->health()->maximum());
        self::assertSame(30.0, $player->character()->regenerationIntervalSeconds);
        self::assertSame('encantamento_de_dano', $player->character()->passive->id);
        self::assertSame(1, $player->inventory()->count());
        self::assertSame('stone_sword', $player->inventory()->at(0)?->definition()->id);
    }

    public function test_alex_starts_with_16_hp_bow_and_cooldown_passive(): void
    {
        $player = self::factory()->create('alex', 'player-1', Vector2::zero(), 6.0, self::idle(...));

        self::assertSame(16.0, $player->health()->current());
        self::assertSame(16.0, $player->health()->maximum());
        self::assertSame(15.0, $player->character()->regenerationIntervalSeconds);
        self::assertSame('reducao_de_cooldown', $player->character()->passive->id);
        self::assertSame('bow', $player->inventory()->at(0)?->definition()->id);
        self::assertNull($player->inventory()->at(1));
    }

    public function test_base_attributes_stay_separate_from_modifiers(): void
    {
        $player = self::factory()->create('steve', 'player-1', Vector2::zero(), 6.0, self::idle(...));

        self::assertSame(['vidaMaxima' => 20.0], $player->baseStats()->values());
        self::assertSame([], $player->modifiers());
        self::assertSame(['vidaMaxima' => 20.0], $player->stats()->values());
    }

    public function test_starting_weapon_executes_through_the_combat_callable(): void
    {
        $attacks = [];
        $player = self::factory()->create('alex', 'player-1', Vector2::zero(), 6.0,
            static function ($definition) use (&$attacks): bool {
                $attacks[] = $definition->id;

                return true;
            });

        self::assertSame(1, $player->inventory()->update(0.0));
        self::assertSame(['bow'], $attacks);
    }

    public function test_unknown_character_fails_before_creating_the_player(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Personagem desconhecido: herobrine.');
        self::factory()->create('herobrine', 'player-1', Vector2::zero(), 6.0, self::idle(...));
    }

    public function test_invalid_player_id_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::factory()->create('steve', ' ', Vector2::zero(), 6.0, self::idle(...));
    }

    public function test_factory_rejects_characters_whose_weapon_is_not_in_the_weapon_catalog(): void
    {
        $characters = new CharacterCatalog([new CharacterDefinition('steve', 'Steve', 20.0, 1.0, 30.0, 'stone_sword',
            new CharacterPassive('p1', 'Passiva', 5, 'dano', ModifierType::PERCENT, 0.1))]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('steve.armaInicial referencia a arma inexistente stone_sword.');
        new PlayerFactory($characters, new WeaponCatalog([WeaponFixtures::definition('torch')]));
    }

    public function test_player_requires_the_starting_weapon_in_the_inventory(): void
    {
        $character = self::characters()->get('steve');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O inventário precisa conter a arma inicial stone_sword.');
        new Player('player-1', Vector2::zero(), 6.0, $character, new CombatInventory);
    }

    private static function factory(): PlayerFactory
    {
        return new PlayerFactory(self::characters(), self::weapons());
    }

    private static function characters(): CharacterCatalog
    {
        return (new CharacterConfigLoader(self::weapons()))->load(dirname(__DIR__, 4).'/config/characters.json');
    }

    private static function weapons(): WeaponCatalog
    {
        return (new WeaponConfigLoader)->load(dirname(__DIR__, 4).'/config/weapons.json');
    }

    private static function idle(): bool
    {
        return false;
    }
}
