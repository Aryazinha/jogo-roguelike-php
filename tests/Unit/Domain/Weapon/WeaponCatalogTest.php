<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Weapon;

use InvalidArgumentException;
use Jogo\Domain\Weapon\WeaponCatalog;
use Jogo\Tests\Support\WeaponFixtures;
use PHPUnit\Framework\TestCase;

final class WeaponCatalogTest extends TestCase
{
    public function test_catalog_preserves_input_order_ids_and_immutable_definitions(): void
    {
        $first = WeaponFixtures::definition('10');
        $second = WeaponFixtures::definition('0');
        $catalog = new WeaponCatalog([$first, $second]);
        self::assertSame(2, $catalog->count());
        self::assertSame([$first, $second], $catalog->all());
        self::assertSame($first, $catalog->get('10'));
        self::assertSame($second, $catalog->get('0'));
        self::assertTrue($catalog->has('0'));
        self::assertFalse($catalog->has('unknown'));
        $copy = $catalog->all();
        array_pop($copy);
        self::assertSame(2, $catalog->count());
    }

    public function test_duplicate_ids_are_rejected_without_silent_overwrite(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new WeaponCatalog([WeaponFixtures::definition('same'), WeaponFixtures::definition('same')]);
    }

    public function test_empty_catalog_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new WeaponCatalog([]);
    }

    public function test_get_of_unknown_id_fails_clearly(): void
    {
        $catalog = new WeaponCatalog([WeaponFixtures::definition()]);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown');
        $catalog->get('unknown');
    }
}
