<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon;

use InvalidArgumentException;
use Jogo\Domain\Pool\ObjectPool;
use Jogo\Domain\Pool\PoolSnapshot;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\Targeting\TargetingResult;

final class ProjectilePool
{
    public const MAX_CAPACITY = 200;

    /** @var ObjectPool<LogicalProjectile> */
    private readonly ObjectPool $pool;

    public function __construct(int $capacity = self::MAX_CAPACITY)
    {
        if ($capacity < 1 || $capacity > self::MAX_CAPACITY) {
            throw new InvalidArgumentException('A capacidade de projéteis deve estar entre 1 e 200.');
        }
        $this->pool = new ObjectPool($capacity, static fn (): LogicalProjectile => new LogicalProjectile,
            static function (LogicalProjectile $projectile): void {
                $projectile->reset();
            });
    }

    /**
     * O ataque só é criado depois de confirmar espaço para toda a rajada.
     *
     * @param  callable(): Attack  $attackFactory
     * @return list<LogicalProjectile>|null
     */
    public function acquireBurst(int $count, callable $attackFactory, Vector2 $origin, TargetingResult $target): ?array
    {
        $attack = null;
        $projectiles = $this->pool->acquireMany($count, static function (LogicalProjectile $projectile, int $index) use (&$attack, $attackFactory, $origin, $target): void {
            $attack ??= $attackFactory();
            $projectile->initialize($attack, $origin, $target);
        });
        if ($projectiles !== null) {
            foreach ($projectiles as $projectile) {
                $projectile->activate();
            }
        }

        return $projectiles;
    }

    public function release(LogicalProjectile $projectile): void
    {
        $this->pool->release($projectile);
    }

    public function clear(): void
    {
        $this->pool->clear();
    }

    public function snapshot(): PoolSnapshot
    {
        return $this->pool->snapshot();
    }
}
