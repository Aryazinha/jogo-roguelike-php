<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Weapon;

use InvalidArgumentException;
use Jogo\Domain\Weapon\CombatInventory;
use Jogo\Domain\Weapon\Weapon;
use Jogo\Domain\Weapon\WeaponDefinition;
use Jogo\Tests\Support\WeaponFixtures;
use LengthException;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CombatInventoryTest extends TestCase
{
    public function test_default_two_slots_addition_queries_and_order(): void
    {
        $inventory = new CombatInventory;
        self::assertSame(2, $inventory->capacity());
        self::assertSame(0, $inventory->count());
        self::assertNull($inventory->at(0));
        self::assertNull($inventory->at(1));
        self::assertNull($inventory->find('missing'));
        $execute = static fn (WeaponDefinition $definition): bool => true;
        self::assertSame(0, $inventory->add(WeaponFixtures::definition('second-alphabetically'), $execute));
        self::assertSame(1, $inventory->add(WeaponFixtures::definition('first-alphabetically'), $execute));
        self::assertSame(2, $inventory->count());
        self::assertSame($inventory->at(0), $inventory->find('second-alphabetically'));
        self::assertSame($inventory->at(1), $inventory->find('first-alphabetically'));
        self::assertSame(['second-alphabetically', 'first-alphabetically'],
            array_map(static fn (Weapon $weapon): string => $weapon->definition()->id, $inventory->weapons()));
        $copy = $inventory->weapons();
        array_pop($copy);
        self::assertSame(2, $inventory->count());
    }

    public function test_third_weapon_is_rejected_without_changing_the_equipped_slots(): void
    {
        $inventory = new CombatInventory;
        $execute = static fn (WeaponDefinition $definition): bool => true;
        $inventory->add(WeaponFixtures::definition('a'), $execute);
        $inventory->add(WeaponFixtures::definition('b'), $execute);
        $before = $inventory->weapons();
        try {
            $inventory->add(WeaponFixtures::definition('c'), $execute);
            self::fail('A terceira arma deve ser rejeitada.');
        } catch (LengthException) {
            self::assertSame($before, $inventory->weapons());
        }
    }

    public function test_duplicate_id_is_rejected_without_resetting_the_existing_weapon(): void
    {
        $inventory = new CombatInventory;
        $execute = static fn (WeaponDefinition $definition): bool => true;
        $inventory->add(WeaponFixtures::definition(), $execute);
        $inventory->update(0.0);
        $weapon = $inventory->at(0);
        self::assertNotNull($weapon);
        try {
            $inventory->add(WeaponFixtures::definition(), $execute);
            self::fail('ID duplicado precisa falhar.');
        } catch (InvalidArgumentException) {
            self::assertSame(1, $inventory->count());
            self::assertSame($weapon, $inventory->at(0));
            self::assertSame(2.0, $weapon->remainingSeconds());
        }
    }

    public function test_replacement_preserves_slot_order_and_creates_fresh_independent_state(): void
    {
        $inventory = new CombatInventory;
        $execute = static fn (WeaponDefinition $definition): bool => true;
        $inventory->add(WeaponFixtures::definition('a'), $execute);
        $inventory->add(WeaponFixtures::definition('b'), $execute);
        $inventory->update(0.0);
        $old = $inventory->at(0);
        $other = $inventory->at(1);
        self::assertNotNull($old);
        self::assertNotNull($other);
        $inventory->replace(0, WeaponFixtures::definition('c', 3.0), $execute);
        $replacement = $inventory->at(0);
        self::assertNotNull($replacement);
        self::assertNotSame($old, $replacement);
        self::assertTrue($replacement->isReady());
        self::assertNull($inventory->find('a'));
        self::assertSame($replacement, $inventory->find('c'));
        self::assertSame($other, $inventory->at(1));
        self::assertSame(['c', 'b'], array_map(static fn (Weapon $weapon): string => $weapon->definition()->id, $inventory->weapons()));
        self::assertSame(1, $inventory->update(0.0));
        self::assertSame(3.0, $replacement->remainingSeconds());
        self::assertSame(2.0, $old->remainingSeconds());
        self::assertSame(2.0, $other->remainingSeconds());
    }

    /** @return iterable<string, array{string}> */
    public static function duplicateReplacements(): iterable
    {
        yield 'same slot id' => ['a'];
        yield 'other slot id' => ['b'];
    }

    #[DataProvider('duplicateReplacements')]
    public function test_replacing_with_an_equipped_id_is_rejected_even_in_its_own_slot(string $id): void
    {
        $inventory = new CombatInventory;
        $execute = static fn (WeaponDefinition $definition): bool => true;
        $inventory->add(WeaponFixtures::definition('a'), $execute);
        $inventory->add(WeaponFixtures::definition('b'), $execute);
        $inventory->update(0.0);
        $before = $inventory->weapons();
        try {
            $inventory->replace(0, WeaponFixtures::definition($id), $execute);
            self::fail('Substituição com ID equipado precisa falhar.');
        } catch (InvalidArgumentException) {
            self::assertSame($before, $inventory->weapons());
            self::assertSame(2.0, $before[0]->remainingSeconds());
        }
    }

    /** @return iterable<string, array{int}> */
    public static function invalidSlots(): iterable
    {
        yield 'negative' => [-1];
        yield 'capacity' => [2];
        yield 'large' => [PHP_INT_MAX];
    }

    #[DataProvider('invalidSlots')]
    public function test_query_of_invalid_slot_fails(int $slot): void
    {
        $this->expectException(OutOfBoundsException::class);
        (new CombatInventory)->at($slot);
    }

    #[DataProvider('invalidSlots')]
    public function test_replacement_of_invalid_slot_fails(int $slot): void
    {
        $this->expectException(OutOfBoundsException::class);
        (new CombatInventory)->replace($slot, WeaponFixtures::definition(), static fn (WeaponDefinition $definition): bool => true);
    }

    public function test_empty_valid_slot_cannot_be_replaced(): void
    {
        $this->expectException(OutOfBoundsException::class);
        (new CombatInventory)->replace(0, WeaponFixtures::definition(), static fn (WeaponDefinition $definition): bool => true);
    }

    public function test_different_durations_update_together_and_in_slot_order(): void
    {
        $inventory = new CombatInventory;
        $trace = [];
        $execute = static function (WeaponDefinition $definition) use (&$trace): bool {
            $trace[] = $definition->id;

            return true;
        };
        $inventory->add(WeaponFixtures::definition('slow', 2.0), $execute);
        $inventory->add(WeaponFixtures::definition('fast', 0.5), $execute);
        self::assertSame(2, $inventory->update(0.0));
        self::assertSame(['slow', 'fast'], $trace);
        self::assertSame(1, $inventory->update(0.5));
        self::assertSame(['slow', 'fast', 'fast'], $trace);
        $slow = $inventory->at(0);
        $fast = $inventory->at(1);
        self::assertNotNull($slow);
        self::assertNotNull($fast);
        self::assertSame(1.5, $slow->remainingSeconds());
        self::assertSame(0.5, $fast->remainingSeconds());
        self::assertSame(2, $inventory->update(1.5));
        self::assertSame(['slow', 'fast', 'fast', 'slow', 'fast'], $trace);
    }

    public function test_same_duration_does_not_link_rejection_acceptance_or_elapsed_time(): void
    {
        $inventory = new CombatInventory;
        $acceptFirst = false;
        $inventory->add(WeaponFixtures::definition('a'), static function (WeaponDefinition $definition) use (&$acceptFirst): bool {
            return $acceptFirst;
        });
        $inventory->add(WeaponFixtures::definition('b'), static fn (WeaponDefinition $definition): bool => true);
        self::assertSame(1, $inventory->update(0.0));
        $first = $inventory->at(0);
        $second = $inventory->at(1);
        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertTrue($first->isReady());
        self::assertFalse($second->isReady());
        self::assertFalse($second->update(0.5));
        self::assertSame(0.0, $first->remainingSeconds());
        self::assertSame(1.5, $second->remainingSeconds());
        $acceptFirst = true;
        self::assertSame(1, $inventory->update(0.0));
        self::assertSame(2.0, $first->remainingSeconds());
        self::assertSame(1.5, $second->remainingSeconds());
    }

    /** @return iterable<string, array{float}> */
    public static function invalidTimes(): iterable
    {
        yield 'negative' => [-1.0];
        yield 'inf' => [INF];
        yield 'negative inf' => [-INF];
        yield 'nan' => [NAN];
    }

    #[DataProvider('invalidTimes')]
    public function test_empty_inventory_still_validates_dt(float $dt): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CombatInventory)->update($dt);
    }

    public function test_capacity_is_configurable_only_at_construction_and_default_remains_two(): void
    {
        self::assertSame(2, (new CombatInventory)->capacity());
        $custom = new CombatInventory(3);
        self::assertSame(3, $custom->capacity());
        self::assertNull($custom->at(2));
        $this->expectException(InvalidArgumentException::class);
        new CombatInventory(0);
    }

    public function test_slots_added_during_execution_wait_until_the_next_update(): void
    {
        $inventory = new CombatInventory;
        $added = false;
        $trace = [];
        $inventory->add(WeaponFixtures::definition('a'), static function (WeaponDefinition $definition) use ($inventory, &$added, &$trace): bool {
            $trace[] = 'a';
            if (! $added) {
                $added = true;
                $inventory->add(WeaponFixtures::definition('b'), static function (WeaponDefinition $definition) use (&$trace): bool {
                    $trace[] = 'b';

                    return true;
                });
            }

            return true;
        });
        self::assertSame(1, $inventory->update(0.0));
        self::assertSame(['a'], $trace);
        self::assertSame(1, $inventory->update(0.0));
        self::assertSame(['a', 'b'], $trace);
    }
}
