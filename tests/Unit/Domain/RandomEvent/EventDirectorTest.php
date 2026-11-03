<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\RandomEvent;

use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Domain\RandomEvent\EventDirector;
use Jogo\Domain\RandomEvent\EventEffect;
use Jogo\Domain\RandomEvent\EventSettings;
use Jogo\Domain\RandomEvent\RandomEventDefinition;
use Jogo\Tests\Support\ControlledRandomIndex;
use PHPUnit\Framework\TestCase;

final class EventDirectorTest extends TestCase
{
    public function test_nothing_happens_before_the_first_gap(): void
    {
        // Sorteio 0: primeiro intervalo = 60 s.
        $director = new EventDirector(self::settings(), new ControlledRandomIndex(0));

        $director->update(59.9);

        self::assertNull($director->active());
        self::assertSame(60.0, $director->nextStartsAt());
        self::assertSame(1.0, $director->spawnCountMultiplier());
    }

    public function test_weights_pick_the_event(): void
    {
        // Pesos 40/40/20 em 0-99: 0-39 horda, 40-79 chuva, 80-99 tesouro.
        foreach ([[0, 'horda'], [39, 'horda'], [40, 'chuva_de_xp'], [79, 'chuva_de_xp'], [80, 'tesouro'], [99, 'tesouro']] as [$roll, $id]) {
            $random = new ControlledRandomIndex(0);
            $director = new EventDirector(self::settings(), $random);
            $random->index = $roll;
            $director->update(60.0);
            self::assertSame($id, $director->active()?->id, (string) $roll);
        }
    }

    public function test_horde_changes_only_the_spawn_and_ends_clean(): void
    {
        $director = new EventDirector(self::settings(), new ControlledRandomIndex(0));

        $director->update(60.0);
        self::assertSame('horda', $director->active()?->id);
        self::assertSame([3.0, 0.5, 1.0, 1.0], self::multipliers($director));

        $director->update(79.99);
        self::assertSame('horda', $director->active()?->id);
        $director->update(80.0);
        self::assertNull($director->active());
        self::assertSame([1.0, 1.0, 1.0, 1.0], self::multipliers($director), 'sem efeito residual');
    }

    public function test_xp_rain_and_treasure_have_their_own_targets(): void
    {
        $random = new ControlledRandomIndex(0);
        $director = new EventDirector(self::settings(), $random);
        $random->index = 50;
        $director->update(60.0);
        self::assertSame([1.0, 1.0, 2.0, 1.0], self::multipliers($director));

        $random = new ControlledRandomIndex(0);
        $director = new EventDirector(self::settings(), $random);
        $random->index = 90;
        $director->update(60.0);
        self::assertSame([1.0, 1.0, 1.0, 2.5], self::multipliers($director));
    }

    public function test_events_never_overlap_and_respect_the_gap(): void
    {
        $director = new EventDirector(self::settings(), new SeededRandomIndex(17));
        $periods = [];
        $current = null;
        for ($step = 0; $step <= 60 * 1800; $step++) {
            $director->update($step / 60);
            $active = $director->active();
            if ($active !== null && $current === null) {
                $current = [$step / 60, $active->id];
            }
            if ($active === null && $current !== null) {
                $periods[] = [$current[0], $step / 60, $current[1]];
                $current = null;
            }
        }

        self::assertGreaterThan(5, count($periods));
        foreach ($periods as $index => [$start, $end, $id]) {
            $duration = ['horda' => 20.0, 'chuva_de_xp' => 30.0, 'tesouro' => 45.0][$id];
            self::assertEqualsWithDelta($duration, $end - $start, 1 / 60 + 1e-9);
            if ($index > 0) {
                $gap = $start - $periods[$index - 1][1];
                self::assertGreaterThanOrEqual(60.0 - 1 / 60, $gap);
                self::assertLessThanOrEqual(120.0 + 1 / 60, $gap);
            }
        }
    }

    public function test_pause_keeps_the_event_running(): void
    {
        $director = new EventDirector(self::settings(), new ControlledRandomIndex(0));
        $director->update(60.0);

        for ($i = 0; $i < 1000; $i++) {
            $director->update(70.0);
        }

        self::assertSame('horda', $director->active()?->id);
        self::assertSame(80.0, $director->activeEndsAt());
    }

    public function test_same_seed_gives_the_same_sequence(): void
    {
        $sequence = static function (int $seed): array {
            $director = new EventDirector(self::settings(), new SeededRandomIndex($seed));
            $seen = [];
            for ($second = 0; $second <= 1800; $second++) {
                $director->update((float) $second);
                $seen[] = $director->active()?->id;
            }

            return $seen;
        };

        self::assertSame($sequence(5), $sequence(5));
    }

    /** @return array{float, float, float, float} */
    private static function multipliers(EventDirector $director): array
    {
        return [$director->spawnCountMultiplier(), $director->spawnIntervalMultiplier(),
            $director->xpMultiplier(), $director->chestChanceMultiplier()];
    }

    private static function settings(): EventSettings
    {
        return new EventSettings(60, 120, [
            new RandomEventDefinition('horda', 'Horda', 'd', 40, 20.0, [new EventEffect('inimigosPorSpawn', 2.0),
                new EventEffect('intervaloSpawnSegundos', -0.5)]),
            new RandomEventDefinition('chuva_de_xp', 'Chuva de XP', 'd', 40, 30.0, [new EventEffect('xpDropado', 1.0)]),
            new RandomEventDefinition('tesouro', 'Tesouro', 'd', 20, 45.0, [new EventEffect('chanceDeBau', 1.5)]),
        ]);
    }
}
