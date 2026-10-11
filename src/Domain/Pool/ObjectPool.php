<?php

declare(strict_types=1);

namespace Jogo\Domain\Pool;

use Closure;
use InvalidArgumentException;
use LogicException;
use Throwable;

/** @template T of object */
final class ObjectPool
{
    /** @var list<T> */
    private array $objects = [];

    /** @var array<int, true> */
    private array $active = [];

    private bool $busy = false;

    private int $peak = 0;

    private int $acquisitions = 0;

    private int $releases = 0;

    private int $refused = 0;

    /** @var Closure(T): void */
    private readonly Closure $reset;

    /**
     * @param  callable(): T  $factory  Cria identidades distintas, apenas no construtor.
     * @param  callable(T): void  $reset  Deve limpar referências e concluir sem lançar.
     */
    public function __construct(private readonly int $capacity, callable $factory, callable $reset)
    {
        if ($capacity < 1) {
            throw new InvalidArgumentException('A capacidade do pool deve ser positiva.');
        }
        $this->reset = Closure::fromCallable($reset);
        $ids = [];
        for ($index = 0; $index < $capacity; $index++) {
            $object = $factory();
            $id = spl_object_id($object);
            if (isset($ids[$id])) {
                throw new InvalidArgumentException('A factory deve criar objetos distintos.');
            }
            ($this->reset)($object);
            $ids[$id] = true;
            $this->objects[] = $object;
        }
    }

    /** @param callable(T): void $initialize
     * @return T|null
     */
    public function acquire(callable $initialize): ?object
    {
        $objects = $this->acquireMany(1, static function (object $object, int $index) use ($initialize): void {
            $initialize($object);
        });

        return $objects === null ? null : $objects[0];
    }

    /**
     * @param  callable(T, int): void  $initialize
     * @return list<T>|null
     */
    public function acquireMany(int $count, callable $initialize): ?array
    {
        $this->assertSafe();
        if ($count < 1) {
            throw new InvalidArgumentException('A reserva deve pedir pelo menos um objeto.');
        }
        if ($count > $this->capacity - count($this->active)) {
            $this->refused++;

            return null;
        }
        $reserved = [];
        foreach ($this->objects as $index => $object) {
            if (! isset($this->active[$index])) {
                $reserved[$index] = $object;
                if (count($reserved) === $count) {
                    break;
                }
            }
        }
        $this->busy = true;
        try {
            try {
                $offset = 0;
                foreach ($reserved as $object) {
                    $initialize($object, $offset++);
                }
            } catch (Throwable $error) {
                // Limpa inclusive objetos reservados cuja inicialização não começou.
                foreach ($reserved as $index => $object) {
                    try {
                        ($this->reset)($object);
                    } catch (Throwable) {
                        // Reset defeituoso: conserva a propriedade e impede reutilização suja.
                        $this->active[$index] = true;
                        $this->peak = max($this->peak, count($this->active));
                    }
                }
                throw $error;
            }
            foreach ($reserved as $index => $object) {
                $this->active[$index] = true;
            }
            $this->acquisitions += $count;
            $this->peak = max($this->peak, count($this->active));

            return array_values($reserved);
        } finally {
            $this->busy = false;
        }
    }

    /** @param T $object */
    public function release(object $object): void
    {
        $this->assertSafe();
        $index = array_search($object, $this->objects, true);
        if ($index === false || ! isset($this->active[$index])) {
            throw new InvalidArgumentException('Objeto estrangeiro ou já liberado.');
        }
        $this->busy = true;
        try {
            ($this->reset)($object);
            unset($this->active[$index]);
            $this->releases++;
        } finally {
            $this->busy = false;
        }
    }

    public function clear(): void
    {
        $this->assertSafe();
        $failure = null;
        foreach (array_keys($this->active) as $index) {
            try {
                $this->release($this->objects[$index]);
            } catch (Throwable $error) {
                $failure ??= $error;
            }
        }
        if ($failure !== null) {
            throw $failure;
        }
    }

    public function snapshot(): PoolSnapshot
    {
        $this->assertSafe();

        return new PoolSnapshot($this->capacity, count($this->active), $this->capacity - count($this->active),
            $this->peak, $this->acquisitions, $this->releases, $this->refused);
    }

    private function assertSafe(): void
    {
        if ($this->busy) {
            throw new LogicException('Operação recursiva durante inicialização ou reset do pool.');
        }
    }
}
