<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Run;

use Jogo\Core\EventBus;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Event\PlayerDied;
use Jogo\Domain\Progression\Experience;
use Jogo\Domain\Run\RunState;
use Jogo\Domain\Value\Vector2;
use LogicException;
use PHPUnit\Framework\TestCase;

final class RunStateTest extends TestCase
{
    private EventBus $events;

    private Experience $experience;

    private RunState $run;

    protected function setUp(): void
    {
        $this->events = new EventBus;
        $this->experience = new Experience;
        $this->run = new RunState('player', 'steve', 20261016, $this->experience);
        $this->run->subscribe($this->events);
    }

    public function test_survival_time_follows_the_fixed_steps(): void
    {
        for ($step = 0; $step < 1800; $step++) {
            $this->run->advance(1.0 / 60.0);
        }

        self::assertSame(30.0, $this->run->survivalSeconds());
    }

    public function test_each_enemy_is_counted_once(): void
    {
        $this->events->publish(self::killed('e1'));
        $this->events->publish(self::killed('e1'));
        $this->events->publish(self::killed('e2'));

        self::assertSame(2, $this->run->enemiesKilled());
    }

    public function test_boss_kill_marks_victory_and_the_run_continues(): void
    {
        $this->events->publish(self::killed('boss-1', boss: true));
        $this->run->advance(5.0);
        $this->events->publish(self::killed('e1'));

        self::assertTrue($this->run->hasWon());
        self::assertFalse($this->run->isEnded());
        self::assertSame(2, $this->run->enemiesKilled());
    }

    public function test_death_ends_the_run_and_produces_the_summary(): void
    {
        $this->experience->gain(9 + 11);
        $this->run->advance(61.75);
        $this->events->publish(self::killed('e1'));
        $this->events->publish(new PlayerDied('player', Vector2::zero(), 'e2', 61.75));

        $summary = $this->run->summary();
        self::assertSame(['steve', 3, 1, 61.75, false, 20261016], [$summary->characterId, $summary->level,
            $summary->enemiesKilled, $summary->survivalSeconds, $summary->won, $summary->seed]);
        self::assertSame(61, $summary->wholeSeconds());
    }

    public function test_nothing_changes_after_the_end(): void
    {
        $this->run->advance(10.0);
        self::assertTrue($this->run->end());

        $this->run->advance(10.0);
        $this->events->publish(self::killed('e1', boss: true));
        $this->run->recordVictory();

        self::assertFalse($this->run->end());
        self::assertSame(10.0, $this->run->survivalSeconds());
        self::assertSame(0, $this->run->enemiesKilled());
        self::assertFalse($this->run->hasWon());
    }

    public function test_death_of_another_player_is_ignored(): void
    {
        $this->events->publish(new PlayerDied('outro', Vector2::zero(), null, 1.0));

        self::assertFalse($this->run->isEnded());
    }

    public function test_summary_is_not_available_before_the_end(): void
    {
        $this->expectException(LogicException::class);
        $this->run->summary();
    }

    private static function killed(string $id, bool $boss = false): EnemyKilled
    {
        return new EnemyKilled($id, $boss ? 'warden' : 'zombie', $boss, Vector2::zero(), 'player', 'bow', 1.0);
    }
}
