<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Entity;

use InvalidArgumentException;
use Jogo\Domain\Entity\Entity;
use Jogo\Domain\Value\Vector2;
use PHPUnit\Framework\TestCase;

final class EntityTest extends TestCase
{
    public function testItKeepsIdentityPositionCollisionRadiusAndActivity(): void
    {
        $entity = $this->createEntity('player-1', new Vector2(10.0, 20.0), 12.0);

        self::assertSame('player-1', $entity->id());
        self::assertTrue($entity->position()->equals(new Vector2(10.0, 20.0)));
        self::assertSame(12.0, $entity->collisionRadius());
        self::assertTrue($entity->isActive());
    }

    public function testItMovesWithoutExposingMutablePositionState(): void
    {
        $entity = $this->createEntity('enemy-1', Vector2::zero(), 8.0);

        $entity->translate(new Vector2(3.0, 4.0));
        self::assertTrue($entity->position()->equals(new Vector2(3.0, 4.0)));

        $entity->moveTo(new Vector2(10.0, 15.0));
        self::assertTrue($entity->position()->equals(new Vector2(10.0, 15.0)));
    }

    public function testItCanBeDeactivated(): void
    {
        $entity = $this->createEntity('projectile-1', Vector2::zero(), 2.0);

        $entity->deactivate();

        self::assertFalse($entity->isActive());
    }

    public function testItRejectsAnEmptyIdentifier(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createEntity('   ', Vector2::zero(), 2.0);
    }

    public function testItRejectsANegativeCollisionRadius(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createEntity('enemy-1', Vector2::zero(), -1.0);
    }

    private function createEntity(string $id, Vector2 $position, float $collisionRadius): Entity
    {
        return new class($id, $position, $collisionRadius) extends Entity {};
    }
}
