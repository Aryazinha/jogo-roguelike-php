<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Pool;

use InvalidArgumentException;
use Jogo\Domain\Pool\ObjectPool;
use Jogo\Tests\Support\PoolItem;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use WeakReference;

final class ObjectPoolTest extends TestCase
{
    /** @return iterable<string, array{int}> */
    public static function invalidCapacities(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    #[DataProvider('invalidCapacities')]
    public function test_invalid_capacity_is_rejected(int $capacity): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::pool($capacity);
    }

    public function test_capacity_ownership_identity_reset_and_historical_metrics(): void
    {
        $created = 0;
        $pool = new ObjectPool(2, static function () use (&$created): PoolItem {
            $created++;

            return new PoolItem;
        },
            static function (PoolItem $item): void {
                $item->reset();
            });
        self::assertSame(2, $created);
        $reference = new stdClass;
        $weak = WeakReference::create($reference);
        $first = $pool->acquire(static function (PoolItem $item) use ($reference): void {
            $item->value = 42;
            $item->reference = $reference;
        });
        self::assertNotNull($first);
        $id = spl_object_id($first);
        $second = $pool->acquire(static function (PoolItem $item): void {
            $item->value = 99;
        });
        self::assertNotNull($second);
        self::assertNotSame($first, $second);
        self::assertNull($pool->acquire(static function (PoolItem $item): void {
            self::fail('Não inicializar quando cheio.');
        }));
        self::assertSame([2, 2, 0, 2, 2, 0, 1], array_values(get_object_vars($pool->snapshot())));
        $pool->release($first);
        unset($reference);
        self::assertNull($weak->get());
        self::assertSame(0, $first->value);
        self::assertNull($first->reference);
        $reused = $pool->acquire(static function (PoolItem $item): void {
            self::assertSame(0, $item->value);
            $item->value = 7;
        });
        self::assertNotNull($reused);
        self::assertSame($id, spl_object_id($reused));
        self::assertSame(2, $created);
        $pool->clear();
        $pool->clear();
        self::assertSame([2, 0, 2, 2, 3, 3, 1], array_values(get_object_vars($pool->snapshot())));
        self::assertSame(0, $second->value);
        self::assertSame(0, $reused->value);
    }

    public function test_foreign_object_and_double_release_are_rejected(): void
    {
        $pool = self::pool(1);
        $object = $pool->acquire(static function (PoolItem $item): void {});
        self::assertNotNull($object);
        $pool->release($object);
        foreach ([$object, new PoolItem] as $invalid) {
            try {
                $pool->release($invalid);
                self::fail('Liberação inválida deveria falhar.');
            } catch (InvalidArgumentException) {
                self::assertSame(1, $pool->snapshot()->available);
            }
        }
    }

    public function test_batch_is_atomic_and_oversized_reservations_are_normal_refusals(): void
    {
        $pool = self::pool(3);
        $objects = $pool->acquireMany(2, static function (PoolItem $item, int $index): void {
            $item->value = $index + 1;
        });
        self::assertNotNull($objects);
        self::assertSame([1, 2], array_column($objects, 'value'));
        foreach ([2, 4] as $count) {
            self::assertNull($pool->acquireMany($count, static function (PoolItem $item, int $index): void {
                self::fail('Lote recusado não inicializa.');
            }));
        }
        self::assertSame(2, $pool->snapshot()->active);
        self::assertSame(1, $pool->snapshot()->available);
        self::assertSame(2, $pool->snapshot()->refusedBatches);
        self::assertSame([1, 2], array_column($objects, 'value'));
    }

    public function test_failed_initialization_resets_every_reserved_object_and_allows_the_same_ids_again(): void
    {
        $pool = self::pool(3);
        $reserved = [];
        try {
            $pool->acquireMany(3, static function (PoolItem $item, int $index) use (&$reserved): void {
                $reserved[] = $item;
                $item->value = 99;
                if ($index === 1) {
                    throw new RuntimeException('initialization');
                }
            });
            self::fail('Inicialização deveria falhar.');
        } catch (RuntimeException $error) {
            self::assertSame('initialization', $error->getMessage());
        }
        self::assertSame([3, 0, 3, 0, 0, 0, 0], array_values(get_object_vars($pool->snapshot())));
        self::assertSame([0, 0], array_column($reserved, 'value'));
        $objects = $pool->acquireMany(3, static function (PoolItem $item, int $index): void {});
        self::assertNotNull($objects);
        self::assertSame(array_map(spl_object_id(...), $reserved), array_map(spl_object_id(...), array_slice($objects, 0, 2)));
    }

    public function test_invalid_batch_size_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::pool(1)->acquireMany(0, static function (PoolItem $item, int $index): void {});
    }

    public function test_factory_must_create_distinct_instances(): void
    {
        $object = new PoolItem;
        $this->expectException(InvalidArgumentException::class);
        new ObjectPool(2, static fn (): PoolItem => $object, static function (PoolItem $item): void {});
    }

    public function test_recursive_acquisition_fails_without_leaking_a_reservation(): void
    {
        $pool = self::pool(2);
        try {
            $pool->acquire(static function (PoolItem $item) use ($pool): void {
                $pool->acquire(static function (PoolItem $other): void {});
            });
            self::fail('Recursão deveria falhar.');
        } catch (LogicException) {
            self::assertSame(2, $pool->snapshot()->available);
        }
    }

    public function test_failed_reset_retains_ownership_until_a_successful_retry(): void
    {
        $control = new PoolItem;
        $pool = new ObjectPool(1, static fn (): PoolItem => new PoolItem,
            static function (PoolItem $item) use ($control): void {
                if ($control->value === 1) {
                    throw new RuntimeException('reset');
                }
                $item->reset();
            });
        $object = $pool->acquire(static function (PoolItem $item): void {
            $item->value = 2;
        });
        self::assertNotNull($object);
        $control->value = 1;
        try {
            $pool->clear();
            self::fail('Reset defeituoso deveria falhar.');
        } catch (RuntimeException) {
            self::assertSame(1, $pool->snapshot()->active);
        }
        self::assertNull($pool->acquire(static function (PoolItem $item): void {}));
        $control->value = 0;
        $pool->clear();
        self::assertSame(0, $object->value);
        self::assertSame(1, $pool->snapshot()->available);
    }

    /** @return ObjectPool<PoolItem> */
    private static function pool(int $capacity): ObjectPool
    {
        return new ObjectPool($capacity, static fn (): PoolItem => new PoolItem, static function (PoolItem $item): void {
            $item->reset();
        });
    }
}
