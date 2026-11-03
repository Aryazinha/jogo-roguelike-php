<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\System;

use Jogo\Core\EventBus;
use Jogo\Domain\Enemy\EnemyProjectile;
use Jogo\Domain\Event\PlayerDamaged;
use Jogo\Domain\System\DamageSystem;
use Jogo\Domain\System\EnemyAttackSystem;
use Jogo\Domain\System\EnemyProjectileSystem;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\MapLayout;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

final class EnemyProjectileSystemTest extends TestCase
{
    public function test_arrow_that_reaches_the_player_hits_and_disappears(): void
    {
        [$world, $system, $hits] = self::setUpWorld();
        $world->addProjectile(self::arrow('arrow-1', 1100.0, -260.0));

        $landed = 0;
        for ($step = 1; $step <= 60; $step++) {
            $landed += $system->update($world, 1 / 60, $step / 60);
        }

        self::assertSame(1, $landed);
        self::assertSame(17.0, $world->player()->health()->current());
        self::assertSame(['arrow-1'], $hits->sources);
        self::assertFalse($world->projectiles()[0]->isActive());
    }

    public function test_fast_arrow_does_not_pass_through_the_player(): void
    {
        // Em um passo de 1 s a flecha vai de x = 1100 para x = 840, por cima do jogador em x = 1000.
        [$world, $system, $hits] = self::setUpWorld();
        $world->addProjectile(self::arrow('arrow-1', 1100.0, -260.0));

        self::assertSame(1, $system->update($world, 1.0, 1.0));
        self::assertSame(['arrow-1'], $hits->sources);
    }

    public function test_arrow_that_misses_keeps_flying_until_it_leaves_the_map(): void
    {
        [$world, $system] = self::setUpWorld();
        $arrow = new EnemyProjectile('arrow-1', new Vector2(1900.0, 1500.0), 4.0, 'enemy-1', 3.0, new Vector2(260.0, 0.0), 5000.0);
        $world->addProjectile($arrow);

        $system->update($world, 0.1, 0.1);
        self::assertTrue($arrow->isActive());
        $system->update($world, 1.0, 1.1);

        self::assertFalse($arrow->isActive());
        self::assertSame(20.0, $world->player()->health()->current());
    }

    public function test_arrow_during_invulnerability_is_spent_without_damage(): void
    {
        [$world, $system, $hits, $attacks] = self::setUpWorld();
        $attacks->strike($world, 'enemy-9', 3.0, 1.0);
        $world->addProjectile(self::arrow('arrow-1', 1050.0, -260.0));

        self::assertSame(1, $system->update($world, 0.2, 1.2));
        self::assertSame(['enemy-9'], $hits->sources);
        self::assertFalse($world->projectiles()[0]->isActive());
    }

    /** @return array{GameWorld, EnemyProjectileSystem, object{sources: list<string>}, EnemyAttackSystem} */
    private static function setUpWorld(): array
    {
        $hits = new class
        {
            /** @var list<string> */
            public array $sources = [];
        };
        $bus = new EventBus;
        $bus->subscribe(PlayerDamaged::class, static function (PlayerDamaged $event) use ($hits): void {
            $hits->sources[] = (string) $event->sourceEntityId;
        });
        $player = new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health(20.0));
        $world = new GameWorld($player, new MapLayout(2000.0, 2000.0, 32.0, 500.0, 700.0));
        $attacks = new EnemyAttackSystem(new DamageSystem($bus), 0.5);

        return [$world, new EnemyProjectileSystem($attacks), $hits, $attacks];
    }

    private static function arrow(string $id, float $x, float $speed): EnemyProjectile
    {
        return new EnemyProjectile($id, new Vector2($x, 1000.0), 4.0, 'enemy-1', 3.0, new Vector2($speed, 0.0), 360.0);
    }
}
