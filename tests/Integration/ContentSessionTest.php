<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Application\Content\ContentSession;
use Jogo\Core\EventBus;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Scenery\Pickup;
use Jogo\Domain\Value\Damage;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

/**
 * C11: todo o conteúdo real rodando junto, sem janela.
 *
 * A "arma" aqui é de teste: a cada 0,25 s tira 8 de vida de todo inimigo a
 * até 120 px do jogador e quebra o cenário em volta. As armas de verdade são
 * da Frente A e entram no mesmo ponto do passo.
 */
final class ContentSessionTest extends TestCase
{
    private const DT = 1 / 20;

    public function test_full_match_runs_every_content_system_until_the_boss_falls(): void
    {
        $match = self::play(seed: 2026, seconds: 720.0);

        self::assertLessThanOrEqual(300, $match['peakAlive']);
        self::assertGreaterThan(0, $match['eventsSeen']);
        self::assertSame(['husk', 'skeleton', 'slime', 'slime_pequeno', 'spider', 'warden', 'zombie'], $match['killedTypes']);
        self::assertTrue($match['bossDefeated'], 'o Warden surge aos 600 s e cai antes dos 720 s');
        self::assertGreaterThanOrEqual(1, $match['chestRewards']);
        self::assertGreaterThan(0, $match['pickedItems']);
    }

    public function test_same_seed_repeats_the_match_and_another_seed_does_not(): void
    {
        self::assertSame(self::play(seed: 5, seconds: 150.0)['checksum'], self::play(seed: 5, seconds: 150.0)['checksum']);
        self::assertNotSame(self::play(seed: 5, seconds: 150.0)['checksum'], self::play(seed: 6, seconds: 150.0)['checksum']);
    }

    /**
     * @return array{peakAlive: int, eventsSeen: int, killedTypes: list<string>, bossDefeated: bool,
     *     chestRewards: int, pickedItems: int, checksum: string}
     */
    private static function play(int $seed, float $seconds): array
    {
        $bus = new EventBus;
        $player = new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(1_000_000.0));
        $session = ContentSession::fromProject(dirname(__DIR__, 2), $player, $seed, $bus);
        $killed = [];
        $bus->subscribe(EnemyKilled::class, static function (EnemyKilled $event) use (&$killed): void {
            $killed[$event->enemyTypeId] = true;
        });

        $peak = 0;
        $events = [];
        $chestRewards = 0;
        $picked = 0;
        $steps = (int) round($seconds / self::DT);
        for ($step = 1; $step <= $steps; $step++) {
            $elapsed = $step * self::DT;
            $session->step(self::DT, $elapsed, 1 + intdiv($step, 600));

            if ($step % 5 === 0) {
                self::fireWeapon($session, $elapsed);
            }
            // O jogador anda num quadrado de 800 px em volta do centro, quebrando o cenário pelo
            // caminho. A cada 10 s vai até o item mais próximo, e corre para o baú quando ele aparece.
            $chest = $session->world->chests()[0] ?? null;
            if ($chest !== null) {
                $player->moveTo($chest->position());
            } elseif ($step % 200 === 0 && $session->world->pickups() !== []) {
                $player->moveTo(self::nearest($session->world->pickups(), $player->position()));
            } elseif ($elapsed < 600.0) {
                // Depois que o Warden surge, o jogador para e luta (ele anda mais devagar que o jogador).
                $player->moveTo(self::patrol($elapsed));
            }

            $peak = max($peak, $session->world->aliveEnemyCount());
            $active = $session->events->active();
            if ($active !== null) {
                $events[$active->id.'@'.$session->events->activeEndsAt()] = true;
            }
            $chestRewards += count($session->chests->takeOpened());
            $picked += count($session->breakables->takePicked());
        }

        $types = array_keys($killed);
        sort($types);

        return ['peakAlive' => $peak, 'eventsSeen' => count($events), 'killedTypes' => $types,
            'bossDefeated' => $session->boss->isDefeated(), 'chestRewards' => $chestRewards, 'pickedItems' => $picked,
            'checksum' => $session->checksum()];
    }

    /** Ponto do quadrado (centro ± 400 px) em que o jogador está, a 60 px/s. */
    private static function patrol(float $elapsed): Vector2
    {
        $corners = [[1600.0, 1600.0], [2400.0, 1600.0], [2400.0, 2400.0], [1600.0, 2400.0]];
        $travelled = fmod($elapsed * 60.0, 3200.0);
        $side = intdiv((int) $travelled, 800);
        $along = ($travelled - $side * 800.0) / 800.0;
        [$x1, $y1] = $corners[$side];
        [$x2, $y2] = $corners[($side + 1) % 4];

        return new Vector2($x1 + ($x2 - $x1) * $along, $y1 + ($y2 - $y1) * $along);
    }

    /** @param non-empty-list<Pickup> $pickups */
    private static function nearest(array $pickups, Vector2 $from): Vector2
    {
        usort($pickups, static fn (Pickup $a, Pickup $b) => $a->position()->distanceTo($from) <=> $b->position()->distanceTo($from));

        return $pickups[0]->position();
    }

    private static function fireWeapon(ContentSession $session, float $elapsed): void
    {
        $player = $session->world->player()->position();
        foreach ($session->world->enemies() as $enemy) {
            if ($enemy->isActive() && $enemy->position()->distanceTo($player) <= 120.0) {
                $session->damage->damageEnemy($enemy, new Damage(8.0, 'player-1', 'arma_de_teste'),
                    $enemy->typeId(), $enemy->isBoss(), $elapsed);
            }
        }
        $session->breakables->damageArea($player, 120.0, 8.0);
        $session->settle();
    }
}
