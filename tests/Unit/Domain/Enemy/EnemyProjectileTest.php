<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Enemy;

use InvalidArgumentException;
use Jogo\Domain\Enemy\EnemyProjectile;
use Jogo\Domain\Value\Vector2;
use PHPUnit\Framework\TestCase;

final class EnemyProjectileTest extends TestCase
{
    public function test_flies_straight_and_returns_where_it_started(): void
    {
        $arrow = self::arrow();

        $start = $arrow->advance(0.5);

        self::assertTrue($start->equals(Vector2::zero()));
        self::assertTrue($arrow->position()->equals(new Vector2(130.0, 0.0)));
        self::assertSame('enemy-7', $arrow->ownerId());
        self::assertSame(3.0, $arrow->damage());
    }

    public function test_disappears_after_its_range(): void
    {
        // 260 px/s e alcance 360: some no segundo passo de 1 s.
        $arrow = self::arrow();

        $arrow->advance(1.0);
        self::assertTrue($arrow->isActive());
        $arrow->advance(1.0);
        self::assertFalse($arrow->isActive());
    }

    public function test_inactive_arrow_does_not_move(): void
    {
        $arrow = self::arrow();
        $arrow->deactivate();

        $arrow->advance(1.0);

        self::assertTrue($arrow->position()->equals(Vector2::zero()));
    }

    public function test_invalid_values_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EnemyProjectile('arrow-1', Vector2::zero(), 4.0, 'enemy-7', 3.0, new Vector2(260.0, 0.0), 0.0);
    }

    private static function arrow(): EnemyProjectile
    {
        return new EnemyProjectile('arrow-1', Vector2::zero(), 4.0, 'enemy-7', 3.0, new Vector2(260.0, 0.0), 360.0);
    }
}
