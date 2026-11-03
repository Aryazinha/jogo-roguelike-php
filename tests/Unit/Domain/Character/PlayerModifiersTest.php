<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Character;

use InvalidArgumentException;
use Jogo\Domain\Character\CharacterDefinition;
use Jogo\Domain\Character\CharacterPassive;
use Jogo\Domain\Character\Player;
use Jogo\Domain\Value\ModifierType;
use Jogo\Domain\Value\StatModifier;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\CombatInventory;
use Jogo\Tests\Support\WeaponFixtures;
use PHPUnit\Framework\TestCase;

final class PlayerModifiersTest extends TestCase
{
    public function test_raising_max_health_also_raises_current_health_by_the_same_amount(): void
    {
        $player = self::player();
        $player->health()->damage(10.0);

        $player->applyModifiers([self::flat('vidaMaxima', 2.0, 'passivos:golden_apple:1')]);

        self::assertSame(22.0, $player->health()->maximum());
        self::assertSame(12.0, $player->health()->current());
        self::assertSame(22.0, $player->stats()->value('vidaMaxima'));
        self::assertSame(20.0, $player->baseStats()->value('vidaMaxima'));
    }

    public function test_lowering_max_health_limits_current_health(): void
    {
        $player = self::player();
        $player->applyModifiers([self::flat('vidaMaxima', 2.0, 'passivos:golden_apple:1')]);

        self::assertTrue($player->removeModifier('passivos:golden_apple:1'));

        self::assertSame(20.0, $player->health()->maximum());
        self::assertSame(20.0, $player->health()->current());
    }

    public function test_dead_player_is_not_revived_by_max_health_changes(): void
    {
        $player = self::player();
        $player->health()->damage(20.0);

        $player->applyModifiers([self::flat('vidaMaxima', 2.0, 'passivos:golden_apple:1')]);

        self::assertTrue($player->health()->isDead());
        self::assertSame(22.0, $player->health()->maximum());
    }

    public function test_each_effect_is_resolved_from_the_base_and_tracked_by_source(): void
    {
        $player = self::player();

        $player->applyModifiers([self::flat('vidaMaxima', 2.0, 'passivos:golden_apple:1')]);
        $player->applyModifiers([self::flat('vidaMaxima', 2.0, 'passivos:golden_apple:2')]);

        self::assertSame(24.0, $player->stats()->value('vidaMaxima'));
        self::assertSame(['passivos:golden_apple:1', 'passivos:golden_apple:2'],
            array_map(static fn (StatModifier $m): string => $m->source, $player->modifiers()));
        self::assertNotNull($player->modifier('passivos:golden_apple:2'));

        self::assertTrue($player->removeModifier('passivos:golden_apple:1'));
        self::assertSame(22.0, $player->stats()->value('vidaMaxima'));
        self::assertFalse($player->removeModifier('passivos:golden_apple:1'));
    }

    public function test_duplicate_source_is_rejected_without_changing_anything(): void
    {
        $player = self::player();
        $player->applyModifiers([self::flat('vidaMaxima', 2.0, 'passivos:golden_apple:1')]);

        try {
            $player->applyModifiers([self::flat('vidaMaxima', 2.0, 'passivos:golden_apple:1')]);
            self::fail('A origem duplicada deveria ser rejeitada.');
        } catch (InvalidArgumentException $error) {
            self::assertStringContainsString('source duplicada', $error->getMessage());
        }

        self::assertCount(1, $player->modifiers());
        self::assertSame(22.0, $player->health()->maximum());
    }

    public function test_invalid_final_value_is_rejected_atomically(): void
    {
        $player = self::player();

        try {
            $player->applyModifiers([
                self::flat('vidaMaxima', 2.0, 'passivos:golden_apple:1'),
                self::flat('vidaMaxima', -30.0, 'teste:negativo'),
            ]);
            self::fail('Vida máxima negativa deveria ser rejeitada.');
        } catch (InvalidArgumentException) {
        }

        self::assertSame([], $player->modifiers());
        self::assertSame(20.0, $player->health()->maximum());
        self::assertSame(20.0, $player->health()->current());
    }

    public function test_weapon_attributes_do_not_belong_to_the_player(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('passiva:steve:5: dano não é um atributo do jogador.');
        self::player()->applyModifiers([new StatModifier('dano', ModifierType::PERCENT, 0.1, 'passiva:steve:5')]);
    }

    public function test_effects_on_missing_bases_are_tracked_but_change_nothing(): void
    {
        $player = self::player();

        $player->applyModifiers([new StatModifier('velocidadeJogador', ModifierType::PERCENT, 0.06, 'passivos:feather:1')]);

        self::assertFalse($player->stats()->has('velocidadeJogador'));
        self::assertNotNull($player->modifier('passivos:feather:1'));
    }

    public function test_optional_bases_receive_their_effects_when_provided(): void
    {
        $player = self::player(['velocidadeJogador' => 100.0, 'raioColeta' => 40.0]);

        $player->applyModifiers([
            new StatModifier('velocidadeJogador', ModifierType::PERCENT, 0.06, 'passivos:feather:1'),
            new StatModifier('raioColeta', ModifierType::PERCENT, 0.15, 'passivos:magnet:1'),
        ]);

        self::assertEqualsWithDelta(106.0, $player->stats()->value('velocidadeJogador'), 1.0E-9);
        self::assertEqualsWithDelta(46.0, $player->stats()->value('raioColeta'), 1.0E-9);
        self::assertSame(100.0, $player->baseStats()->value('velocidadeJogador'));
    }

    public function test_only_known_optional_bases_are_accepted(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('vidaMaxima não é uma base opcional do jogador.');
        self::player(['vidaMaxima' => 50.0]);
    }

    public function test_regeneration_keeps_working_after_max_health_grows(): void
    {
        $player = self::player();
        $player->health()->damage(5.0);
        $player->applyModifiers([self::flat('vidaMaxima', 2.0, 'passivos:golden_apple:1')]);

        self::assertSame(1.0, $player->regenerate(30.0));
        self::assertSame(18.0, $player->health()->current());
    }

    /** @param array<string, float> $optionalBase */
    private static function player(array $optionalBase = []): Player
    {
        $character = new CharacterDefinition('steve', 'Steve', 20.0, 1.0, 30.0, 'stone_sword',
            new CharacterPassive('encantamento_de_dano', 'Passiva', 5, 'dano', ModifierType::PERCENT, 0.1));
        $inventory = new CombatInventory;
        $inventory->add(WeaponFixtures::definition('stone_sword'), static fn (): bool => false);

        return new Player('player-1', Vector2::zero(), 6.0, $character, $inventory, $optionalBase);
    }

    private static function flat(string $attribute, float $value, string $source): StatModifier
    {
        return new StatModifier($attribute, ModifierType::FLAT, $value, $source);
    }
}
