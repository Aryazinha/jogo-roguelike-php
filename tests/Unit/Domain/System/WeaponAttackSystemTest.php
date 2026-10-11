<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\System;

use InvalidArgumentException;
use Jogo\Domain\Event\EnemyHit;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\Attack;
use Jogo\Domain\Weapon\LogicalProjectile;
use Jogo\Domain\Weapon\Targeting\TargetingResult;
use Jogo\Domain\Weapon\TntCharge;
use Jogo\Domain\Weapon\WeaponDefinition;
use Jogo\Tests\Support\CombatHarness;
use Jogo\Tests\Support\ControlledRandomIndex;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class WeaponAttackSystemTest extends TestCase
{
    /** @return iterable<string, array{float, float, bool}> */
    public static function swordPositions(): iterable
    {
        yield 'front' => [40.0, 0.0, true];
        yield 'rear' => [-40.0, 0.0, false];
        yield 'side' => [0.0, 40.0, false];
        yield 'range boundary' => [90.0, 0.0, true];
        yield 'out of range' => [90.001, 0.0, false];
        yield 'upper angle boundary' => [45.0, 45.0 * sqrt(3.0), true];
        yield 'lower angle boundary' => [45.0, -45.0 * sqrt(3.0), true];
        yield 'outside angle' => [45.0, 45.0 * sqrt(3.0) + 0.001, false];
        yield 'origin' => [0.0, 0.0, true];
    }

    #[DataProvider('swordPositions')]
    public function test_sword_sector_uses_exact_configured_range_and_angle(float $x, float $y, bool $hit): void
    {
        $h = new CombatHarness;
        $target = $h->world->add('target', $x, $y);
        $h->equip('stone_sword');
        self::assertSame(1, $h->step(0.0, 0.0));
        self::assertSame($hit ? 97.5 : 100.0, $target->health()->current());
        self::assertCount($hit ? 1 : 0, $h->hits);
        self::assertSame(0, $h->step(0.0, 0.0));
        self::assertSame($hit ? 97.5 : 100.0, $target->health()->current());
    }

    public function test_sword_rotates_with_facing_hits_multiple_enemies_once_and_filters_non_targets(): void
    {
        $h = new CombatHarness;
        $h->world->facing = new Vector2(0.0, 1.0);
        $h->world->add('z', 0.0, 20.0, 100.0);
        $h->world->add('a', 0.0, 90.0);
        $h->world->add('wrong-direction', 90.0, 0.0);
        $h->world->add('pickup', 0.0, 1.0, kind: 'pickup');
        $h->world->add('inactive', 0.0, 1.0)->deactivate();
        $h->world->add('dead', 0.0, 1.0)->health()->damage(100.0);
        $h->world->add('unresolved', 0.0, 1.0);
        $h->world->unresolved['unresolved'] = true;
        $h->equip('stone_sword');
        $h->step(0.0, 0.0);
        self::assertSame(['a', 'z'], array_column($h->hits, 'enemyId'));
        self::assertSame(100.0, $h->world->targets['pickup']->health()->current());
    }

    /** @return iterable<string, array{string, bool}> */
    public static function noTargets(): iterable
    {
        yield 'sword' => ['stone_sword', true];
        yield 'torch' => ['torch', true];
        yield 'bow' => ['bow', false];
        yield 'tnt' => ['tnt', false];
    }

    #[DataProvider('noTargets')]
    public function test_no_target_policy_controls_cooldown_acceptance(string $id, bool $accepted): void
    {
        $h = new CombatHarness;
        $h->equip($id);
        self::assertSame($accepted ? 1 : 0, $h->step(0.0, 0.0));
        $weapon = $h->inventory->find($id);
        self::assertNotNull($weapon);
        self::assertSame(! $accepted, $weapon->isReady());
        self::assertSame($accepted ? $h->catalog->get($id)->cooldownSeconds : 0.0, $weapon->remainingSeconds());
        self::assertSame([], $h->hits);
        self::assertSame([], $h->combat->projectiles());
        self::assertSame([], $h->combat->charges());
        if (! $accepted) {
            $h->world->add('late-target', 50.0);
            self::assertSame(1, $h->step(0.0, 0.0));
        }
    }

    public function test_torch_pulse_hits_centers_including_boundary_in_id_order_once(): void
    {
        $h = new CombatHarness;
        $h->world->add('z', -70.0);
        $h->world->add('a', 0.0, -30.0);
        $h->world->add('outside', 70.01, 0.0, 100.0);
        $h->world->add('inactive', 0.0)->deactivate();
        $h->world->add('dead', 0.0)->health()->damage(100.0);
        $h->world->add('pickup', 0.0, kind: 'pickup');
        $h->equip('torch');
        $h->step(0.0, 0.0);
        self::assertSame(['a', 'z'], array_column($h->hits, 'enemyId'));
        self::assertSame([1.2, 1.2], array_column($h->hits, 'appliedDamage'));
        $h->step(0.1, 0.1);
        self::assertCount(2, $h->hits);
    }

    public function test_bow_moves_by_speed_times_dt_and_hits_first_surface_even_across_a_large_step(): void
    {
        $h = new CombatHarness;
        $h->world->add('z-nearest', 40.0, 0.0, 1.0);
        $h->world->add('a-farther', 60.0, 0.0, 1.0);
        $h->equip('bow');
        $h->step(0.0, 0.0);
        $projectile = $h->combat->projectiles()[0];
        self::assertSame('z-nearest', $projectile->target->entityId);
        self::assertSame(0.0, $projectile->position()->x());
        $h->step(0.05, 0.05);
        self::assertSame(21.0, $projectile->position()->x());
        self::assertSame(21.0, $projectile->traveled());
        $h->step(0.1, 0.15);
        self::assertSame(['z-nearest'], array_column($h->hits, 'enemyId'));
        self::assertSame(96.8, $h->world->targets['z-nearest']->health()->current());
        self::assertSame(100.0, $h->world->targets['a-farther']->health()->current());
        self::assertFalse($projectile->isActive());
        self::assertSame([], $h->combat->projectiles());
        $h->step(0.1, 0.25);
        self::assertCount(1, $h->hits);
    }

    public function test_bow_trajectory_is_not_homing_and_stops_at_maximum_range(): void
    {
        $h = new CombatHarness;
        $target = $h->world->add('target', 100.0);
        $h->equip('bow');
        $h->step(0.0, 0.0);
        $p = $h->combat->projectiles()[0];
        $snapshot = $p->target;
        $target->moveTo(new Vector2(100.0, 100.0));
        $h->step(1.0, 1.0);
        // Após fim de alcance, a mesma instância já está limpa no pool da A9.
        self::assertSame(0.0, $p->traveled());
        self::assertTrue($p->position()->equals(Vector2::zero()));
        self::assertTrue($snapshot->position->equals(new Vector2(100.0, 0.0)));
        self::assertFalse($p->isActive());
        self::assertSame([], $h->hits);
    }

    public function test_bow_includes_impact_at_last_point_and_filters_invalid_colliders(): void
    {
        $h = new CombatHarness;
        $h->world->add('boundary', 320.0);
        $h->world->add('pickup', 10.0, kind: 'pickup');
        $h->world->add('inactive', 20.0)->deactivate();
        $h->world->add('dead', 30.0)->health()->damage(100.0);
        $h->world->add('unresolved', 40.0);
        $h->world->unresolved['unresolved'] = true;
        $h->equip('bow');
        // Para a mira, a porta de leitura não deve anunciar um alvo que não possa resolver.
        $h->world->kinds['unresolved'] = 'breakable';
        $h->step(0.0, 0.0);
        $h->step(1.0, 1.0);
        self::assertSame(['boundary'], array_column($h->hits, 'enemyId'));
    }

    public function test_projectile_count_creates_parallel_siblings_but_does_not_multiply_damage_to_one_enemy(): void
    {
        $h = new CombatHarness;
        $h->world->add('target', 50.0);
        $definition = self::bow(3);
        $h->inventory->add($definition, $h->combat);
        $h->step(0.0, 0.0);
        $projectiles = $h->combat->projectiles();
        self::assertCount(3, $projectiles);
        self::assertSame($projectiles[0]->attack, $projectiles[1]->attack);
        foreach ($projectiles as $projectile) {
            self::assertTrue($projectile->target->direction->equals(new Vector2(1.0, 0.0)));
        }
        $h->step(0.2, 0.2);
        self::assertCount(1, $h->hits);
        self::assertSame(96.8, $h->world->targets['target']->health()->current());
        self::assertSame([], $h->combat->projectiles());
    }

    public function test_projectile_ties_use_id_after_first_contact_distance(): void
    {
        $h = new CombatHarness;
        $h->world->add('z', 20.0, 1.0, 1.0);
        $h->world->add('a', 20.0, -1.0, 1.0);
        $h->world->add('aim', 100.0);
        $h->equip('bow');
        $h->step(0.0, 0.0);
        // A mira já foi fixada; deslocar o alvo não muda a direção do projétil.
        $h->world->targets['a']->moveTo(new Vector2(20.0, 0.0));
        $h->world->targets['z']->moveTo(new Vector2(20.0, 0.0));
        $h->step(0.1, 0.1);
        self::assertSame(['a'], array_column($h->hits, 'enemyId'));
    }

    public function test_tnt_waits_full_delay_and_explodes_at_snapshot_even_when_original_target_dies(): void
    {
        $random = new ControlledRandomIndex(0);
        $h = new CombatHarness($random);
        $selected = $h->world->add('a-selected', 200.0);
        $h->world->add('b-boundary', 310.0);
        $h->world->add('c-inside', 200.0, 50.0);
        $h->world->add('outside', 310.01);
        $h->world->add('pickup', 200.0, kind: 'pickup');
        $h->equip('tnt');
        $h->step(0.0, 0.0);
        $charge = $h->combat->charges()[0];
        self::assertSame(1.0, $charge->remainingSeconds());
        self::assertTrue($charge->position->equals(new Vector2(200.0, 0.0)));
        $selected->moveTo(new Vector2(500.0, 500.0));
        $selected->health()->damage(100.0);
        $selected->deactivate();
        $h->step(0.5, 0.5);
        self::assertSame([], $h->hits);
        self::assertSame(0.5, $charge->remainingSeconds());
        $h->step(0.5, 1.0);
        self::assertSame(['b-boundary', 'c-inside'], array_column($h->hits, 'enemyId'));
        self::assertSame([6.0, 6.0], array_column($h->hits, 'appliedDamage'));
        self::assertSame([1.0, 1.0], array_column($h->hits, 'elapsedSeconds'));
        self::assertTrue($charge->isDetonated());
        self::assertSame([], $h->combat->charges());
        $h->step(0.5, 1.5);
        self::assertCount(2, $h->hits);
    }

    public function test_tnt_zero_delay_is_resolved_in_the_birth_step(): void
    {
        $h = new CombatHarness;
        $h->world->add('target', 10.0);
        $h->inventory->add(new WeaponDefinition('zero-delay', 'Fake TNT', 6.0, 4.0, 'inimigo_aleatorio', 'area', 260.0,
            explosionRadius: 110.0, detonationDelaySeconds: 0.0), $h->combat);
        $h->step(0.0, 0.0);
        self::assertCount(1, $h->hits);
        self::assertSame([], $h->combat->charges());
    }

    public function test_events_preserve_source_weapon_target_effective_damage_type_boss_and_hit_before_death(): void
    {
        $h = new CombatHarness;
        $target = $h->world->add('boss-fake', 20.0, health: 2.0);
        $h->world->bosses['boss-fake'] = true;
        $h->equip('stone_sword');
        $h->step(0.0, 1.5);
        self::assertCount(1, $h->hits);
        self::assertCount(1, $h->deaths);
        $hit = $h->hits[0];
        self::assertSame('player', $hit->sourceEntityId);
        self::assertSame('stone_sword', $hit->weaponId);
        self::assertSame('boss-fake', $hit->enemyId);
        self::assertSame('fake_type', $hit->enemyTypeId);
        self::assertSame(2.0, $hit->appliedDamage);
        self::assertSame(0.0, $hit->remainingHealth);
        self::assertSame(1.5, $hit->elapsedSeconds);
        self::assertTrue($h->deaths[0]->isBoss);
        self::assertSame('player', $h->deaths[0]->sourceEntityId);
        self::assertSame('stone_sword', $h->deaths[0]->weaponId);
        self::assertFalse($target->isActive());
    }

    public function test_two_equipped_weapons_attack_automatically_with_independent_cooldowns(): void
    {
        $h = new CombatHarness;
        $h->world->add('target', 20.0);
        $h->equip('stone_sword');
        $h->equip('torch');
        self::assertSame(2, $h->step(0.0, 0.0));
        self::assertSame(['stone_sword', 'torch'], array_column($h->hits, 'weaponId'));
        self::assertSame(1, $h->step(0.8, 0.8));
        self::assertSame(['stone_sword', 'torch', 'torch'], array_column($h->hits, 'weaponId'));
        self::assertSame(1.2, $h->inventory->find('stone_sword')?->remainingSeconds());
        self::assertSame(0.8, $h->inventory->find('torch')?->remainingSeconds());
    }

    public function test_fatal_hit_prevents_a_second_weapon_from_damaging_the_same_dead_target(): void
    {
        $h = new CombatHarness;
        $h->world->add('target', 10.0, health: 2.0);
        $h->equip('stone_sword');
        $h->equip('torch');
        $h->step(0.0, 0.0);
        self::assertSame(['stone_sword'], array_column($h->hits, 'weaponId'));
        self::assertCount(1, $h->deaths);
    }

    public function test_listener_failure_does_not_replay_a_consumed_projectile_or_leave_the_system_locked(): void
    {
        $h = new CombatHarness;
        $h->world->add('target', 20.0);
        $h->equip('bow');
        $h->step(0.0, 0.0);
        $h->events->subscribe(EnemyHit::class, static function (EnemyHit $hit): void {
            throw new RuntimeException('Listener de teste.');
        });
        try {
            $h->step(0.1, 0.1);
            self::fail('Falha do listener deve propagar.');
        } catch (RuntimeException $error) {
            self::assertSame('Listener de teste.', $error->getMessage());
        }
        self::assertSame([], $h->combat->projectiles());
        self::assertSame(0, $h->step(0.1, 0.2));
        self::assertCount(1, $h->hits);
    }

    /** @return iterable<string, array{float, float}> */
    public static function invalidTimes(): iterable
    {
        yield 'negative dt' => [-1.0, 0.0];
        yield 'nan dt' => [NAN, 0.0];
        yield 'infinite dt' => [INF, 0.0];
        yield 'negative time' => [0.0, -1.0];
        yield 'nan time' => [0.0, NAN];
        yield 'infinite time' => [0.0, INF];
    }

    #[DataProvider('invalidTimes')]
    public function test_invalid_time_does_not_start_weapons(float $dt, float $time): void
    {
        $h = new CombatHarness;
        $h->equip('torch');
        try {
            $h->step($dt, $time);
            self::fail('Tempo inválido precisa falhar.');
        } catch (InvalidArgumentException) {
            self::assertTrue($h->inventory->find('torch')?->isReady());
            self::assertSame([], $h->hits);
        }
    }

    public function test_logical_time_cannot_go_backwards(): void
    {
        $h = new CombatHarness;
        $h->step(0.0, 2.0);
        $this->expectException(LogicException::class);
        $h->step(0.0, 1.0);
    }

    public function test_execution_outside_the_step_is_rejected(): void
    {
        $h = new CombatHarness;
        $this->expectException(LogicException::class);
        ($h->combat)($h->catalog->get('torch'));
    }

    public function test_attack_identity_claims_each_target_at_most_once(): void
    {
        $h = new CombatHarness;
        $attack = new Attack(1, 'player', $h->catalog->get('torch'));
        self::assertTrue($attack->claim('a'));
        self::assertFalse($attack->claim('a'));
        self::assertTrue($attack->claim('b'));
        self::assertTrue((new Attack(2, 'player', $attack->definition))->claim('a'));
    }

    public function test_invalid_transient_formats_are_rejected(): void
    {
        $h = new CombatHarness;
        $attack = new Attack(1, 'player', $h->catalog->get('torch'));
        $this->expectException(InvalidArgumentException::class);
        new LogicalProjectile($attack, Vector2::zero(), new TargetingResult(new Vector2(1.0, 0.0), Vector2::zero(), null));
    }

    public function test_invalid_charge_format_is_rejected(): void
    {
        $h = new CombatHarness;
        $this->expectException(InvalidArgumentException::class);
        new TntCharge(new Attack(1, 'player', $h->catalog->get('bow')), Vector2::zero());
    }

    private static function bow(int $count): WeaponDefinition
    {
        return new WeaponDefinition('parallel-bow', 'Fake bow', 3.2, 1.8, 'inimigo_mais_proximo', 'projetil', 320.0,
            projectileCount: $count, projectileSpeed: 420.0);
    }
}
