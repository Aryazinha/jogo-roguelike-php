<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Progression;

use InvalidArgumentException;
use Jogo\Domain\Progression\WeaponLevels;
use PHPUnit\Framework\TestCase;

final class WeaponLevelsTest extends TestCase
{
    public function test_new_weapon_starts_at_level_one_and_goes_up_one_level_at_a_time(): void
    {
        $levels = new WeaponLevels;
        $levels->register('bow');

        self::assertSame(1, $levels->level('bow'));
        $levels->promote('bow', 2);
        self::assertSame(2, $levels->level('bow'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('bow só pode subir para o nível 3.');
        $levels->promote('bow', 4);
    }

    public function test_weapon_cannot_be_registered_twice(): void
    {
        $levels = new WeaponLevels;
        $levels->register('bow');

        $this->expectException(InvalidArgumentException::class);
        $levels->register('bow');
    }

    public function test_evolution_keeps_the_base_id_and_tracks_the_equipped_id(): void
    {
        $levels = new WeaponLevels;
        $levels->register('tnt');

        $levels->evolve('tnt', 'mega_tnt');

        self::assertTrue($levels->isEvolved('tnt'));
        self::assertSame('mega_tnt', $levels->currentId('tnt'));
        self::assertSame('tnt', $levels->baseIdOf('mega_tnt'));
        self::assertNull($levels->baseIdOf('tnt'));
    }

    public function test_removed_weapon_can_be_acquired_again(): void
    {
        $levels = new WeaponLevels;
        $levels->register('torch');
        $levels->remove('torch');

        self::assertSame([], $levels->baseIds());
        $levels->register('torch');
        self::assertSame(1, $levels->level('torch'));
    }
}
