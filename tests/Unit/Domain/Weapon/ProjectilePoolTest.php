<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Weapon;

use InvalidArgumentException;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\Attack;
use Jogo\Domain\Weapon\LogicalProjectile;
use Jogo\Domain\Weapon\ProjectilePool;
use Jogo\Domain\Weapon\Targeting\TargetingResult;
use Jogo\Tests\Support\ProjectileFixtures;
use Jogo\Tests\Support\WeaponFixtures;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WeakReference;

final class ProjectilePoolTest extends TestCase
{
    /** @return iterable<string, array{int}> */
    public static function invalidCapacities(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'above maximum' => [201];
    }

    #[DataProvider('invalidCapacities')]
    public function test_invalid_capacities_fail(int $capacity): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ProjectilePool($capacity);
    }

    public function test_exactly_200_instances_are_available_and_201st_never_initializes(): void
    {
        $pool = new ProjectilePool;
        self::assertSame(200, $pool->snapshot()->capacity);
        $calls = 0;
        $objects = $pool->acquireBurst(200, static function () use (&$calls): Attack {
            $calls++;

            return new Attack(1, 'source', ProjectileFixtures::bow(count: 200));
        }, Vector2::zero(), self::target());
        self::assertNotNull($objects);
        self::assertCount(200, array_unique(array_map(spl_object_id(...), $objects)));
        self::assertSame(1, $calls);
        self::assertNull($pool->acquireBurst(1, static function (): Attack {
            self::fail('Não criar ataque recusado.');
        }, Vector2::zero(), self::target()));
        self::assertSame(200, $pool->snapshot()->peakActive);
        $ids = array_map(spl_object_id(...), $objects);
        $pool->clear();
        $reused = $pool->acquireBurst(200, static fn (): Attack => new Attack(2, 'source', ProjectileFixtures::bow(count: 200)), Vector2::zero(), self::target());
        self::assertNotNull($reused);
        self::assertSame($ids, array_map(spl_object_id(...), $reused));
        self::assertSame(2, $objects[0]->attack->id);
    }

    public function test_release_removes_every_reference_and_reuse_has_another_weapon_target_and_direction(): void
    {
        $pool = new ProjectilePool(1);
        $attack = new Attack(1, 'old-source', ProjectileFixtures::bow('old-weapon', speed: 1.1));
        $origin = new Vector2(3.0, 4.0);
        $target = self::target();
        $weak = [WeakReference::create($attack), WeakReference::create($origin), WeakReference::create($target)];
        $objects = $pool->acquireBurst(1, static fn (): Attack => $attack, $origin, $target);
        self::assertNotNull($objects);
        $p = $objects[0];
        $id = spl_object_id($p);
        for ($step = 0; $step < 11; $step++) {
            $p->advance(1.0 / 60.0);
        }
        $pool->release($p);
        unset($attack, $origin, $target);
        foreach ($weak as $reference) {
            self::assertNull($reference->get());
        }
        self::assertFalse($p->isActive());
        self::assertSame(0.0, $p->traveled());
        self::assertTrue($p->position()->equals(Vector2::zero()));
        foreach (['attack', 'origin', 'target'] as $field) {
            try {
                $p->__get($field);
                self::fail('Referência antiga deveria estar indisponível.');
            } catch (LogicException) {
                self::assertFalse($p->isActive());
            }
        }
        $newAttack = new Attack(2, 'new-source', ProjectileFixtures::bow('new-weapon', speed: 0.3));
        $newOrigin = new Vector2(-3.0, -4.0);
        $newTarget = new TargetingResult(new Vector2(0.0, -1.0), new Vector2(-3.0, -100.0), 'other-target');
        $reused = $pool->acquireBurst(1, static fn (): Attack => $newAttack, $newOrigin, $newTarget);
        self::assertNotNull($reused);
        self::assertSame($id, spl_object_id($reused[0]));
        $fresh = new LogicalProjectile($newAttack, $newOrigin, $newTarget);
        for ($step = 0; $step < 120; $step++) {
            $p->advance(1.0 / 60.0);
            $fresh->advance(1.0 / 60.0);
            self::assertSame($fresh->traveled(), $p->traveled());
            self::assertEquals($fresh->position(), $p->position());
        }
        self::assertSame('new-weapon', $p->attack->definition->id);
        self::assertSame('other-target', $p->target->entityId);
    }

    public function test_initialization_failure_rolls_back_all_slots_and_foreign_or_double_release_fails(): void
    {
        $pool = new ProjectilePool(2);
        try {
            $pool->acquireBurst(2, static fn (): Attack => new Attack(1, 'source', WeaponFixtures::definition()), Vector2::zero(), self::target());
            self::fail('Formato inválido deveria falhar.');
        } catch (InvalidArgumentException) {
            self::assertSame(2, $pool->snapshot()->available);
        }
        $objects = $pool->acquireBurst(2, static fn (): Attack => new Attack(2, 'source', ProjectileFixtures::bow(count: 2)), Vector2::zero(), self::target());
        self::assertNotNull($objects);
        $pool->clear();
        foreach ([$objects[0], new LogicalProjectile] as $invalid) {
            try {
                $pool->release($invalid);
                self::fail('Liberação inválida deveria falhar.');
            } catch (InvalidArgumentException) {
                self::assertSame(0, $pool->snapshot()->active);
            }
        }
    }

    public function test_standalone_projectile_keeps_the_original_trajectory_and_maximum_range(): void
    {
        $attack = new Attack(1, 'source', ProjectileFixtures::bow());
        $p = new LogicalProjectile($attack, Vector2::zero(), self::target());
        $p->advance(0.05);
        self::assertSame(21.0, $p->traveled());
        $p->advance(1.0);
        self::assertSame(320.0, $p->traveled());
        self::assertEquals(new Vector2(320.0, 0.0), $p->position());
        self::assertTrue($p->hasReachedRange());
        $p->consume();
        $p->advance(10.0);
        self::assertSame(320.0, $p->traveled());
    }

    public function test_initialization_is_not_activation_and_public_references_cannot_be_overwritten(): void
    {
        $p = new LogicalProjectile;
        $p->initialize(new Attack(1, 'source', ProjectileFixtures::bow()), Vector2::zero(), self::target());
        self::assertFalse($p->isActive());
        $p->advance(1.0);
        self::assertSame(0.0, $p->traveled());
        $p->activate();
        self::assertTrue($p->isActive());
        $this->expectException(LogicException::class);
        $p->__set('attack', new Attack(2, 'other', ProjectileFixtures::bow()));
    }

    private static function target(): TargetingResult
    {
        return new TargetingResult(new Vector2(1.0, 0.0), new Vector2(100.0, 0.0), 'old-target');
    }
}
