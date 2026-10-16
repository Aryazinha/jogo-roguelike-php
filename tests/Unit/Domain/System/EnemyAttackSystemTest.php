<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\System;

use Jogo\Core\EventBus;
use Jogo\Domain\Enemy\Enemy;
use Jogo\Domain\Event\PlayerDamaged;
use Jogo\Domain\Event\PlayerDied;
use Jogo\Domain\System\DamageSystem;
use Jogo\Domain\System\EnemyAttackSystem;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\MapLayout;
use Jogo\Tests\Support\EnemyFixtures;
use Jogo\Tests\Support\FakeDamageable;
use PHPUnit\Framework\TestCase;

final class EnemyAttackSystemTest extends TestCase
{
    /** @var list<PlayerDamaged> */
    private array $hits = [];

    /** @var list<PlayerDied> */
    private array $deaths = [];

    public function test_touching_enemy_deals_its_contact_damage(): void
    {
        [$world, $attacks] = $this->setUpWorld();
        // Jogador em (1000, 1000) com raio 16; Zombie com raio 14 a 25 px: se tocam.
        $world->addEnemy(self::zombie('enemy-1', 1025.0));

        $applied = $attacks->update($world, 1.0);

        self::assertSame(3.0, $applied);
        self::assertSame(17.0, $world->player()->health()->current());
        self::assertSame('enemy-1', $this->hits[0]->sourceEntityId);
    }

    public function test_exact_tangency_counts_and_a_gap_does_not(): void
    {
        [$world, $attacks] = $this->setUpWorld();
        $world->addEnemy(self::zombie('enemy-1', 1030.0));
        self::assertSame(3.0, $attacks->update($world, 1.0));

        [$world, $attacks] = $this->setUpWorld();
        $world->addEnemy(self::zombie('enemy-1', 1030.5));
        self::assertSame(0.0, $attacks->update($world, 1.0));
    }

    public function test_invulnerability_blocks_new_hits_until_it_expires(): void
    {
        [$world, $attacks] = $this->setUpWorld();
        $world->addEnemy(self::zombie('enemy-1', 1020.0));

        self::assertSame(3.0, $attacks->update($world, 1.0));
        self::assertSame(0.0, $attacks->update($world, 1.2));
        self::assertTrue($attacks->isInvulnerable(1.49));
        self::assertSame(3.0, $attacks->update($world, 1.5));
        self::assertCount(2, $this->hits);
    }

    public function test_crowd_only_lands_the_strongest_hit_of_the_step(): void
    {
        [$world, $attacks] = $this->setUpWorld();
        $world->addEnemy(self::zombie('enemy-1', 1020.0));
        $world->addEnemy(new Enemy('enemy-2', EnemyFixtures::slime(), new Vector2(980.0, 1000.0)));
        $world->addEnemy(self::zombie('enemy-3', 1010.0));

        self::assertSame(4.0, $attacks->update($world, 1.0));
        self::assertSame(['enemy-2'], array_map(static fn (PlayerDamaged $h) => $h->sourceEntityId, $this->hits));
    }

    public function test_tie_goes_to_the_lowest_id_whatever_the_order(): void
    {
        [$world, $attacks] = $this->setUpWorld();
        $world->addEnemy(self::zombie('enemy-b', 1020.0));
        $world->addEnemy(self::zombie('enemy-a', 980.0));

        $attacks->update($world, 1.0);

        self::assertSame('enemy-a', $this->hits[0]->sourceEntityId);
    }

    public function test_dead_enemy_does_not_hit(): void
    {
        [$world, $attacks] = $this->setUpWorld();
        $zombie = self::zombie('enemy-1', 1020.0);
        $world->addEnemy($zombie);
        $zombie->health()->damage(100.0);

        self::assertSame(0.0, $attacks->update($world, 1.0));
    }

    public function test_player_dies_once_and_is_not_hit_after_death(): void
    {
        [$world, $attacks] = $this->setUpWorld(health: 5.0, invulnerability: 0.0);
        $world->addEnemy(self::zombie('enemy-1', 1020.0));

        $attacks->update($world, 1.0);
        $attacks->update($world, 2.0);
        $attacks->update($world, 3.0);

        self::assertCount(2, $this->hits);
        self::assertCount(1, $this->deaths);
        self::assertSame('enemy-1', $this->deaths[0]->sourceEntityId);
    }

    public function test_specific_attack_shares_the_invulnerability(): void
    {
        [$world, $attacks] = $this->setUpWorld();
        $world->addEnemy(self::zombie('enemy-1', 1020.0));

        self::assertSame(3.0, $attacks->strike($world, 'arrow-1', 3.0, 1.0));
        self::assertSame(0.0, $attacks->update($world, 1.1), 'o contato não pode somar com a flecha');
        self::assertSame(0.0, $attacks->strike($world, 'arrow-2', 3.0, 1.2));
        self::assertSame(0.0, $attacks->strike($world, 'arrow-3', 0.0, 9.0));
    }

    public function test_pause_does_not_consume_invulnerability(): void
    {
        // Pausado, o tempo de simulação fica parado; repetir o mesmo instante não libera golpe.
        [$world, $attacks] = $this->setUpWorld();
        $world->addEnemy(self::zombie('enemy-1', 1020.0));

        $attacks->update($world, 1.0);
        for ($i = 0; $i < 100; $i++) {
            self::assertSame(0.0, $attacks->update($world, 1.0));
        }
    }

    /** @return array{GameWorld, EnemyAttackSystem} */
    private function setUpWorld(float $health = 20.0, float $invulnerability = 0.5): array
    {
        $this->hits = [];
        $this->deaths = [];
        $bus = new EventBus;
        $bus->subscribe(PlayerDamaged::class, function (PlayerDamaged $event): void {
            $this->hits[] = $event;
        });
        $bus->subscribe(PlayerDied::class, function (PlayerDied $event): void {
            $this->deaths[] = $event;
        });
        $player = new FakeDamageable('player-1', Vector2::zero(), 16.0, new Health($health));
        $world = new GameWorld($player, new MapLayout(2000.0, 2000.0, 32.0, 500.0, 700.0));

        return [$world, new EnemyAttackSystem(new DamageSystem($bus), $invulnerability)];
    }

    private static function zombie(string $id, float $x): Enemy
    {
        return new Enemy($id, EnemyFixtures::common('zombie'), new Vector2($x, 1000.0));
    }
}
