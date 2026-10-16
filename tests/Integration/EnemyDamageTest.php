<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Core\EventBus;
use Jogo\Domain\Enemy\Enemy;
use Jogo\Domain\Event\EnemyHit;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\System\DamageSystem;
use Jogo\Domain\Value\Damage;
use Jogo\Domain\Value\Vector2;
use Jogo\Tests\Support\EnemyFixtures;
use PHPUnit\Framework\TestCase;

/** Inimigo real da C passando pelo DamageSystem da A, sem adaptadores. */
final class EnemyDamageTest extends TestCase
{
    public function test_zombie_dies_once_and_the_event_carries_its_type(): void
    {
        $bus = new EventBus;
        $hits = [];
        $kills = [];
        $bus->subscribe(EnemyHit::class, static function (EnemyHit $event) use (&$hits): void {
            $hits[] = $event;
        });
        $bus->subscribe(EnemyKilled::class, static function (EnemyKilled $event) use (&$kills): void {
            $kills[] = $event;
        });
        $damage = new DamageSystem($bus);
        $zombie = new Enemy('enemy-1', EnemyFixtures::common('zombie', 6), new Vector2(5.0, 5.0));
        $target = $zombie->asTarget();

        // Zombie tem 13 de vida: 2,5 + 2,5 + 10 passa do total na terceira espada.
        foreach ([2.5, 2.5, 10.0, 10.0] as $second => $amount) {
            $damage->damageEnemy($target->entity, new Damage($amount, 'player-1', 'stone_sword'),
                $target->enemyTypeId, $target->isBoss, (float) $second);
        }

        self::assertCount(3, $hits);
        self::assertSame(8.0, $hits[2]->appliedDamage);
        self::assertCount(1, $kills);
        self::assertSame('zombie', $kills[0]->enemyTypeId);
        self::assertFalse($kills[0]->isBoss);
        self::assertTrue($zombie->health()->isDead());
    }
}
