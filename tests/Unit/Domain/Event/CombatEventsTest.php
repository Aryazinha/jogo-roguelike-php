<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Event;

use Error;
use InvalidArgumentException;
use Jogo\Domain\Event\DomainEvent;
use Jogo\Domain\Event\EnemyHit;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Event\PlayerDamaged;
use Jogo\Domain\Event\PlayerDied;
use Jogo\Domain\Value\Vector2;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class CombatEventsTest extends TestCase
{
    /** @return iterable<string, array{class-string<DomainEvent>}> */
    public static function eventClasses(): iterable
    {
        foreach ([PlayerDamaged::class, EnemyHit::class, EnemyKilled::class, PlayerDied::class] as $class) {
            yield $class => [$class];
        }
    }

    /** @param class-string<DomainEvent> $class */
    #[DataProvider('eventClasses')]
    public function test_events_are_final_readonly_and_preserve_the_ratified_signature(string $class): void
    {
        $reflection = new ReflectionClass($class);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        $parameters = $constructor->getParameters();
        self::assertSame(array_keys(self::arguments($class)), array_map(static fn ($parameter): string => $parameter->getName(), $parameters));
        $event = self::createEvent($class);
        self::assertInstanceOf(DomainEvent::class, $event);
        self::assertEquals(self::arguments($class), get_object_vars($event));
    }

    /** @return iterable<string, array{class-string<DomainEvent>, string, float}> */
    public static function invalidNumbers(): iterable
    {
        foreach (self::eventClasses() as [$class]) {
            foreach (self::arguments($class) as $field => $default) {
                if (! is_float($default)) {
                    continue;
                }
                foreach (['negative' => -1.0, 'inf' => INF, 'negative inf' => -INF, 'nan' => NAN] as $label => $value) {
                    yield "$class:$field:$label" => [$class, $field, $value];
                }
                if ($field === 'appliedDamage') {
                    yield "$class:$field:zero" => [$class, $field, 0.0];
                }
            }
        }
    }

    /** @param class-string<DomainEvent> $class */
    #[DataProvider('invalidNumbers')]
    public function test_numeric_fields_reject_invalid_values(string $class, string $field, float $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::createEvent($class, [$field => $value]);
    }

    /** @return iterable<string, array{class-string<DomainEvent>, string, string}> */
    public static function invalidIds(): iterable
    {
        foreach (self::eventClasses() as [$class]) {
            foreach (array_keys(self::arguments($class)) as $field) {
                if (! str_ends_with($field, 'Id')) {
                    continue;
                }
                yield "$class:$field:empty" => [$class, $field, ''];
                yield "$class:$field:blank" => [$class, $field, " \t\n"];
            }
        }
    }

    /** @param class-string<DomainEvent> $class */
    #[DataProvider('invalidIds')]
    public function test_present_ids_cannot_be_blank(string $class, string $field, string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::createEvent($class, [$field => $value]);
    }

    /** @param class-string<DomainEvent> $class */
    #[DataProvider('eventClasses')]
    public function test_null_origin_zero_time_and_zero_remaining_health_are_valid(string $class): void
    {
        $changes = ['sourceEntityId' => null, 'elapsedSeconds' => 0.0];
        $arguments = self::arguments($class);
        if (array_key_exists('weaponId', $arguments)) {
            $changes['weaponId'] = null;
        }
        if (array_key_exists('remainingHealth', $arguments)) {
            $changes['remainingHealth'] = 0.0;
        }
        $event = self::createEvent($class, $changes);
        $payload = get_object_vars($event);
        foreach ($changes as $field => $value) {
            self::assertSame($value, $payload[$field]);
        }
    }

    public function test_event_fields_and_the_position_are_immutable(): void
    {
        $event = new PlayerDamaged('target', new Vector2(-2.0, 3.0), 1.5, 8.5, null, 0.0);
        $shifted = $event->position->add(new Vector2(10.0, 10.0));
        self::assertSame(-2.0, $event->position->x());
        self::assertSame(3.0, $event->position->y());
        self::assertSame(8.0, $shifted->x());
        $this->expectException(Error::class);
        (new \ReflectionProperty($event, 'appliedDamage'))->setValue($event, 20.0);
    }

    /**
     * @param  class-string<DomainEvent>  $class
     * @param  array<string, mixed>  $changes
     */
    private static function createEvent(string $class, array $changes = []): DomainEvent
    {
        $event = (new ReflectionClass($class))->newInstanceArgs(array_replace(self::arguments($class), $changes));
        self::assertInstanceOf(DomainEvent::class, $event);

        return $event;
    }

    /**
     * @param  class-string<DomainEvent>  $class
     * @return array<string, string|float|bool|Vector2|null>
     */
    private static function arguments(string $class): array
    {
        return match ($class) {
            PlayerDamaged::class => ['playerId' => 'target', 'position' => Vector2::zero(), 'appliedDamage' => 2.5,
                'remainingHealth' => 7.5, 'sourceEntityId' => 'attacker', 'elapsedSeconds' => 1.25],
            EnemyHit::class => ['enemyId' => 'target', 'enemyTypeId' => 'fixture-type', 'position' => Vector2::zero(),
                'appliedDamage' => 2.5, 'remainingHealth' => 7.5, 'sourceEntityId' => 'attacker', 'weaponId' => 'fixture-weapon', 'elapsedSeconds' => 1.25],
            EnemyKilled::class => ['enemyId' => 'target', 'enemyTypeId' => 'fixture-type', 'isBoss' => true,
                'position' => Vector2::zero(), 'sourceEntityId' => 'attacker', 'weaponId' => 'fixture-weapon', 'elapsedSeconds' => 1.25],
            PlayerDied::class => ['playerId' => 'target', 'position' => Vector2::zero(), 'sourceEntityId' => 'attacker', 'elapsedSeconds' => 1.25],
            default => throw new InvalidArgumentException('Evento fora dos quatro casos deste teste.'),
        };
    }
}
