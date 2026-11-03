<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Progression;

use InvalidArgumentException;
use Jogo\Core\EventBus;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Event\LevelUp;
use Jogo\Domain\Progression\EnemyXpTable;
use Jogo\Domain\Progression\Experience;
use Jogo\Domain\Progression\ExperienceSystem;
use Jogo\Domain\Progression\XpRange;
use Jogo\Domain\Random\RandomIndex;
use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Domain\Value\Vector2;
use Jogo\Tests\Support\ControlledRandomIndex;
use PHPUnit\Framework\TestCase;

final class ExperienceSystemTest extends TestCase
{
    public function test_xp_is_drawn_inside_the_enemy_range(): void
    {
        $random = new ControlledRandomIndex(0);
        $system = self::system($random);

        self::assertSame(3, $system->onEnemyKilled(self::killed('e1', 'zombie')));
        $random->index = 1;
        self::assertSame(4, $system->onEnemyKilled(self::killed('e2', 'zombie')));

        self::assertSame([2, 2], $random->counts);
        self::assertSame(7, $system->experience()->totalXp());
    }

    public function test_same_enemy_is_never_rewarded_twice(): void
    {
        $system = self::system(new ControlledRandomIndex(0));

        self::assertSame(3, $system->onEnemyKilled(self::killed('e1', 'zombie')));
        self::assertSame(0, $system->onEnemyKilled(self::killed('e1', 'zombie')));

        self::assertSame(3, $system->experience()->totalXp());
    }

    public function test_unknown_enemy_type_fails_without_changing_progress(): void
    {
        $system = self::system(new ControlledRandomIndex(0));

        try {
            $system->onEnemyKilled(self::killed('e1', 'creeper'));
            self::fail('Tipo sem XP deveria falhar.');
        } catch (InvalidArgumentException $error) {
            self::assertSame('Tipo de inimigo sem XP: creeper.', $error->getMessage());
        }

        self::assertSame(0, $system->experience()->totalXp());
        self::assertSame(3, $system->onEnemyKilled(self::killed('e1', 'zombie')));
    }

    public function test_enemy_killed_events_from_the_bus_grant_xp_and_publish_one_level_up_per_level(): void
    {
        $bus = new EventBus;
        $levelUps = [];
        $bus->subscribe(LevelUp::class, static function (LevelUp $event) use (&$levelUps): void {
            $levelUps[] = $event;
        });
        $system = new ExperienceSystem('player-1', new Experience, new EnemyXpTable(['warden' => new XpRange(150, 150)]),
            new ControlledRandomIndex(0), $bus);
        $system->subscribe();

        $bus->publish(new EnemyKilled('boss-1', 'warden', true, Vector2::zero(), 'player-1', 'bow', 300.0));

        self::assertSame(9, $system->experience()->level());
        self::assertSame(22, $system->experience()->currentXp());
        self::assertSame([2, 3, 4, 5, 6, 7, 8, 9], array_map(static fn (LevelUp $e): int => $e->level, $levelUps));
        self::assertSame('player-1', $levelUps[0]->playerId);
        self::assertSame(150, $levelUps[0]->totalXp);
        self::assertSame(300.0, $levelUps[7]->elapsedSeconds);
    }

    public function test_direct_grant_publishes_level_up(): void
    {
        $bus = new EventBus;
        $levels = [];
        $bus->subscribe(LevelUp::class, static function (LevelUp $event) use (&$levels): void {
            $levels[] = $event->level;
        });
        $system = new ExperienceSystem('player-1', new Experience, self::table(), new ControlledRandomIndex(0), $bus);

        self::assertSame([2], $system->grant(9, 12.5));
        self::assertSame([2], $levels);
    }

    public function test_same_seed_and_kills_produce_the_same_progression(): void
    {
        $run = static function (): array {
            $system = self::system(new SeededRandomIndex(20261015));
            $types = ['spider', 'zombie', 'husk', 'zombie', 'spider', 'husk', 'husk', 'zombie'];
            $gains = [];
            foreach ($types as $index => $type) {
                $gains[] = $system->onEnemyKilled(self::killed("e{$index}", $type));
            }

            return [$gains, $system->experience()->level(), $system->experience()->currentXp()];
        };

        self::assertSame($run(), $run());
    }

    private static function system(RandomIndex $random): ExperienceSystem
    {
        return new ExperienceSystem('player-1', new Experience, self::table(), $random, new EventBus);
    }

    private static function table(): EnemyXpTable
    {
        return new EnemyXpTable([
            'spider' => new XpRange(2, 3),
            'zombie' => new XpRange(3, 4),
            'husk' => new XpRange(7, 10),
        ]);
    }

    private static function killed(string $id, string $type): EnemyKilled
    {
        return new EnemyKilled($id, $type, false, Vector2::zero(), 'player-1', 'stone_sword', 1.0);
    }
}
