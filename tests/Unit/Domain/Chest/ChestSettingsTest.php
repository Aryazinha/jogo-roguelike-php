<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Chest;

use Jogo\Domain\Chest\ChestRarity;
use Jogo\Domain\Chest\ChestSettings;
use Jogo\Domain\Chest\HealingItem;
use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Tests\Support\ControlledRandomIndex;
use PHPUnit\Framework\TestCase;

final class ChestSettingsTest extends TestCase
{
    public function test_rarity_limits_follow_the_probabilities(): void
    {
        $settings = self::settings();

        // Sorteio de 0 a 9999: madeira até 8499, bronze até 9499, ouro de 9500 a 9999.
        self::assertSame('madeira', $settings->drawRarity(new ControlledRandomIndex(0))->id);
        self::assertSame('madeira', $settings->drawRarity(new ControlledRandomIndex(8499))->id);
        self::assertSame('bronze', $settings->drawRarity(new ControlledRandomIndex(8500))->id);
        self::assertSame('bronze', $settings->drawRarity(new ControlledRandomIndex(9499))->id);
        self::assertSame('ouro', $settings->drawRarity(new ControlledRandomIndex(9500))->id);
        self::assertSame('ouro', $settings->drawRarity(new ControlledRandomIndex(9999))->id);
    }

    public function test_many_draws_match_the_configured_shares(): void
    {
        $settings = self::settings();
        $random = new SeededRandomIndex(123);
        $counts = ['madeira' => 0, 'bronze' => 0, 'ouro' => 0];

        for ($i = 0; $i < 20_000; $i++) {
            $counts[$settings->drawRarity($random)->id]++;
        }

        self::assertEqualsWithDelta(0.85, $counts['madeira'] / 20_000, 0.01);
        self::assertEqualsWithDelta(0.10, $counts['bronze'] / 20_000, 0.01);
        self::assertEqualsWithDelta(0.05, $counts['ouro'] / 20_000, 0.01);
    }

    public function test_kill_drop_chance_and_treasure_multiplier(): void
    {
        $settings = self::settings();

        // 0,4% = sorteios 0 a 39; com Tesouro (× 2,5) = 0 a 99.
        self::assertTrue($settings->rollKillDrop(new ControlledRandomIndex(39)));
        self::assertFalse($settings->rollKillDrop(new ControlledRandomIndex(40)));
        self::assertTrue($settings->rollKillDrop(new ControlledRandomIndex(99), 2.5));
        self::assertFalse($settings->rollKillDrop(new ControlledRandomIndex(100), 2.5));
        self::assertFalse($settings->rollKillDrop(new ControlledRandomIndex(0), 0.0));
    }

    private static function settings(): ChestSettings
    {
        return new ChestSettings([
            new ChestRarity('madeira', 'Bau de Madeira', 0.85, 'upgrade_de_arma', 1),
            new ChestRarity('bronze', 'Bau de Bronze', 0.10, 'upgrade_de_arma', 2),
            new ChestRarity('ouro', 'Bau de Ouro', 0.05, 'upgrade_de_arma', 3),
        ], 1, 0.004, new HealingItem('steak', 'Steak', 5.0));
    }
}
