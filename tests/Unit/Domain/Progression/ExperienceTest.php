<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Progression;

use InvalidArgumentException;
use Jogo\Domain\Progression\Experience;
use Jogo\Domain\Progression\LevelFormula;
use PHPUnit\Framework\TestCase;

final class ExperienceTest extends TestCase
{
    public function test_starts_at_level_one_without_xp(): void
    {
        $experience = new Experience;

        self::assertSame(1, $experience->level());
        self::assertSame(0, $experience->currentXp());
        self::assertSame(0, $experience->totalXp());
        self::assertSame(9, $experience->requiredForNextLevel());
    }

    public function test_xp_accumulates_without_leveling_before_the_requirement(): void
    {
        $experience = new Experience;

        self::assertSame([], $experience->gain(3));
        self::assertSame([], $experience->gain(5));

        self::assertSame(1, $experience->level());
        self::assertSame(8, $experience->currentXp());
        self::assertSame(8, $experience->totalXp());
    }

    public function test_reaching_the_requirement_levels_up_and_keeps_the_excess(): void
    {
        $experience = new Experience;

        self::assertSame([2], $experience->gain(12));

        self::assertSame(2, $experience->level());
        self::assertSame(3, $experience->currentXp());
        self::assertSame(11, $experience->requiredForNextLevel());
    }

    public function test_one_big_gain_can_reach_several_levels(): void
    {
        $experience = new Experience;

        self::assertSame([2, 3, 4], $experience->gain(9 + 11 + 13 + 1));

        self::assertSame(4, $experience->level());
        self::assertSame(1, $experience->currentXp());
        self::assertSame(34, $experience->totalXp());
    }

    public function test_boundary_between_levels_15_and_16(): void
    {
        $experience = self::atLevel(15);
        self::assertSame(37, $experience->requiredForNextLevel());

        self::assertSame([], $experience->gain(36));
        self::assertSame([16], $experience->gain(1));
        self::assertSame(42, $experience->requiredForNextLevel());
    }

    public function test_boundary_between_levels_30_and_31(): void
    {
        $experience = self::atLevel(30);
        self::assertSame(112, $experience->requiredForNextLevel());

        self::assertSame([31], $experience->gain(112));
        self::assertSame(121, $experience->requiredForNextLevel());
        self::assertSame(0, $experience->currentXp());
    }

    public function test_zero_gain_changes_nothing(): void
    {
        $experience = new Experience;

        self::assertSame([], $experience->gain(0));
        self::assertSame(0, $experience->totalXp());
    }

    public function test_negative_gain_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Experience)->gain(-1);
    }

    private static function atLevel(int $level): Experience
    {
        $experience = new Experience;
        $total = 0;
        for ($current = Experience::STARTING_LEVEL; $current < $level; $current++) {
            $total += LevelFormula::requiredXp($current);
        }
        $experience->gain($total);
        self::assertSame($level, $experience->level());
        self::assertSame(0, $experience->currentXp());

        return $experience;
    }
}
