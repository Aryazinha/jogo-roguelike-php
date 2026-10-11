<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use Jogo\Domain\Event\EnemyHit;
use Jogo\Domain\Value\ModifierType;
use Jogo\Domain\Value\StatModifier;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\ProjectilePool;
use Jogo\Tests\Support\CombatHarness;
use Jogo\Tests\Support\ProgressionFixtures;
use Jogo\Tests\Support\ProjectileFixtures;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProjectilePoolCombatTest extends TestCase
{
    public function test_full_pool_preserves_cooldown_ids_and_objects_until_a_later_attempt(): void
    {
        $h = new CombatHarness(pool: new ProjectilePool(1));
        $target = $h->world->add('target', 100.0);
        $h->inventory->add(ProjectileFixtures::bow(), $h->combat);
        $weapon = $h->inventory->at(0);
        self::assertNotNull($weapon);
        self::assertSame(1, $h->step(0.0, 0.0));
        $projectile = $h->combat->projectiles()[0];
        $id = spl_object_id($projectile);
        $oldAttack = $projectile->attack;
        // A tentativa ocorre antes do impacto que libera a única vaga neste passo.
        self::assertSame(0, $h->step(0.25, 0.25));
        self::assertSame(1, $oldAttack->id);
        self::assertSame(96.8, $target->health()->current());
        self::assertCount(1, $h->hits);
        self::assertSame(0.0, $weapon->remainingSeconds());
        self::assertSame(1, $h->combat->poolSnapshot()->refusedBatches);
        self::assertSame(1, $h->combat->poolSnapshot()->available);
        self::assertSame(1, $h->step(0.0, 0.25));
        $new = $h->combat->projectiles()[0];
        self::assertSame($id, spl_object_id($new));
        self::assertSame(2, $new->attack->id);
        self::assertSame(0.0, $new->traveled());
        self::assertNotSame($oldAttack, $new->attack);
        self::assertSame(0.01, $weapon->remainingSeconds());
    }

    public function test_burst_with_only_one_free_slot_is_refused_without_partial_attack_or_damage(): void
    {
        $h = new CombatHarness(pool: new ProjectilePool(3));
        $target = $h->world->add('target', 100.0);
        $h->inventory->add(ProjectileFixtures::bow(count: 2), $h->combat);
        $h->step(0.0, 0.0);
        $old = $h->combat->projectiles();
        self::assertCount(2, $old);
        self::assertSame(0, $h->step(0.01, 0.01));
        self::assertSame($old, $h->combat->projectiles());
        self::assertSame(2, $h->combat->poolSnapshot()->acquisitions);
        self::assertSame(100.0, $target->health()->current());
        self::assertCount(0, $h->hits);
        $h->step(0.25, 0.26);
        self::assertCount(1, $h->hits);
        self::assertSame(1, $h->step(0.0, 0.26));
        self::assertCount(2, $h->combat->projectiles());
        self::assertSame(2, $h->combat->projectiles()[0]->attack->id);
    }

    public function test_count_larger_than_200_after_upgrade_is_refused_without_consuming_an_id(): void
    {
        $h = new CombatHarness;
        $h->world->add('target', 100.0);
        $h->equip('bow');
        $service = ProgressionFixtures::service($h);
        $service->apply(0, [new StatModifier('projeteis', ModifierType::FLAT, 200.0, 'too-many')]);
        self::assertSame(0, $h->step(0.0, 0.0));
        self::assertSame(0.0, $h->inventory->at(0)?->remainingSeconds());
        self::assertSame(0, $h->combat->poolSnapshot()->acquisitions);
        self::assertTrue($service->remove(0, 'too-many'));
        self::assertSame(1, $h->step(0.0, 0.0));
        self::assertSame(1, $h->combat->projectiles()[0]->attack->id);
    }

    public function test_impact_resolves_before_release_and_errors_do_not_replay_damage(): void
    {
        $h = new CombatHarness(pool: new ProjectilePool(1));
        $h->world->add('target', 10.0);
        $h->equip('bow');
        $h->step(0.0, 0.0);
        $p = $h->combat->projectiles()[0];
        $h->events->subscribe(EnemyHit::class, static function (EnemyHit $hit) use ($h, $p): void {
            self::assertSame(1, $h->combat->poolSnapshot()->active);
            self::assertSame(0, $h->combat->poolSnapshot()->available);
            self::assertSame($hit->weaponId, $p->attack->definition->id);
            try {
                $h->combat->clear();
                self::fail('Limpeza durante dano deveria falhar.');
            } catch (LogicException) {
                self::assertSame(1, $h->combat->poolSnapshot()->active);
            }
            throw new RuntimeException('listener');
        });
        try {
            $h->step(0.1, 0.1);
            self::fail('Listener deveria lançar.');
        } catch (RuntimeException $error) {
            self::assertSame('listener', $error->getMessage());
        }
        self::assertSame(0, $h->combat->poolSnapshot()->active);
        self::assertSame(1, $h->combat->poolSnapshot()->available);
        self::assertSame(0.0, $p->traveled());
        $h->step(0.1, 0.2);
        self::assertCount(1, $h->hits);
    }

    public function test_range_release_and_cleanup_cancel_charges_and_keep_historical_counters(): void
    {
        $h = new CombatHarness(pool: new ProjectilePool(2));
        $target = $h->world->add('target', 100.0);
        $h->equip('bow');
        $h->equip('tnt');
        $h->step(0.0, 0.0);
        $p = $h->combat->projectiles()[0];
        $target->moveTo(new Vector2(100.0, 100.0));
        $h->step(0.8, 0.8);
        self::assertFalse($p->isActive());
        self::assertSame(1, $h->combat->poolSnapshot()->releases);
        self::assertCount(1, $h->combat->charges());
        $h->combat->clear();
        $h->combat->clear();
        self::assertSame([], $h->combat->projectiles());
        self::assertSame([], $h->combat->charges());
        self::assertSame(2, $h->combat->poolSnapshot()->available);
        self::assertSame(1, $h->combat->poolSnapshot()->peakActive);
    }

    public function test_collision_counters_exclude_selection_and_administrative_queries(): void
    {
        $h = new CombatHarness;
        $h->world->add('target', 10.0);
        $h->world->add('pickup', 10.0, kind: 'pickup');
        $h->equip('bow');
        $h->step(0.0, 0.0);
        self::assertSame(0, $h->combat->collisionSnapshot()->totalCandidates);
        $before = $h->combat->collisionSnapshot();
        $h->view->candidates(Vector2::zero(), new Vector2(20.0, 0.0));
        $h->view->neighbors(Vector2::zero(), 320.0);
        self::assertEquals($before, $h->combat->collisionSnapshot());
        $h->step(0.1, 0.1);
        $metrics = $h->combat->collisionSnapshot();
        self::assertSame([2, 1, 2, 1, 2, 1], array_values(get_object_vars($metrics)));
        $h->step(0.1, 0.2);
        self::assertSame([2, 1, 0, 0, 2, 1], array_values(get_object_vars($h->combat->collisionSnapshot())));
    }
}
