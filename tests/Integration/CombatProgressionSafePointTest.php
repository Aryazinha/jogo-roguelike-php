<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Application\Progression\ProgressionSession;
use Jogo\Core\GameLoop;
use Jogo\Domain\Value\Vector2;
use Jogo\Tests\Support\CombatHarness;
use PHPUnit\Framework\TestCase;

final class CombatProgressionSafePointTest extends TestCase
{
    public function test_kill_during_combat_levels_up_and_grants_the_passive_after_combat_without_exception(): void
    {
        $harness = new CombatHarness;
        $session = ProgressionSession::start(dirname(__DIR__, 2).'/config', 'steve', 'player', Vector2::zero(), 6.0,
            100.0, 40.0, 7, $harness->events, $harness->combat, $harness->combat);
        $harness->world->add('warden-1', 50.0, health: 1.0);
        $harness->world->types['warden-1'] = 'warden';
        $harness->world->bosses['warden-1'] = true;

        $sword = $session->player->inventory()->weapons()[0];
        $harness->combat->update($session->player->inventory(), GameLoop::STEP_SECONDS, GameLoop::STEP_SECONDS);

        self::assertCount(1, $harness->deaths);
        self::assertSame(9, $session->experience->experience()->level());
        self::assertSame([5], $session->characterPassive->pendingLevels());
        self::assertSame(2.5, $sword->definition()->damage);

        $session->update(GameLoop::STEP_SECONDS);

        self::assertSame([5], $session->characterPassive->grantedLevels());
        self::assertSame([], $session->characterPassive->pendingLevels());
        self::assertEqualsWithDelta(2.5 * 1.1, $sword->definition()->damage, 1.0E-12);
        self::assertTrue($session->run->hasWon());
        self::assertSame(8, $session->levelUps->pendingLevels());
    }
}
