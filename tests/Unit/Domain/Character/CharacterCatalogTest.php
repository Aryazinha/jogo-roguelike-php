<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Character;

use InvalidArgumentException;
use Jogo\Domain\Character\CharacterCatalog;
use Jogo\Domain\Character\CharacterDefinition;
use Jogo\Domain\Character\CharacterPassive;
use Jogo\Domain\Value\ModifierType;
use PHPUnit\Framework\TestCase;

final class CharacterCatalogTest extends TestCase
{
    public function test_lookup_by_id_keeps_the_original_order(): void
    {
        $catalog = new CharacterCatalog([self::character('steve', 'p1'), self::character('alex', 'p2')]);

        self::assertTrue($catalog->has('alex'));
        self::assertFalse($catalog->has('herobrine'));
        self::assertSame('alex', $catalog->get('alex')->id);
        self::assertSame(2, $catalog->count());
    }

    public function test_unknown_id_fails_with_the_requested_id(): void
    {
        $catalog = new CharacterCatalog([self::character('steve', 'p1')]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Personagem desconhecido: herobrine.');
        $catalog->get('herobrine');
    }

    public function test_duplicate_character_id_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ID de personagem duplicado: steve.');
        new CharacterCatalog([self::character('steve', 'p1'), self::character('steve', 'p2')]);
    }

    public function test_each_character_needs_its_own_passive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A passiva p1 deve ser única por personagem.');
        new CharacterCatalog([self::character('steve', 'p1'), self::character('alex', 'p1')]);
    }

    public function test_empty_catalog_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CharacterCatalog([]);
    }

    public function test_invalid_definition_values_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('vida deve ser maior que zero.');
        new CharacterDefinition('steve', 'Steve', 0.0, 1.0, 30.0, 'stone_sword', self::passive('p1'));
    }

    public function test_passive_requires_a_positive_level_interval(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('aCadaNiveis deve ser um inteiro >= 1.');
        new CharacterPassive('p1', 'Passiva', 0, 'dano', ModifierType::PERCENT, 0.1);
    }

    private static function character(string $id, string $passiveId): CharacterDefinition
    {
        return new CharacterDefinition($id, ucfirst($id), 20.0, 1.0, 30.0, 'stone_sword', self::passive($passiveId));
    }

    private static function passive(string $id): CharacterPassive
    {
        return new CharacterPassive($id, 'Passiva de teste', 5, 'dano', ModifierType::PERCENT, 0.1);
    }
}
