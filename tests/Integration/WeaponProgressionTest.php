<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use InvalidArgumentException;
use Jogo\Core\GameLoop;
use Jogo\Domain\Event\EnemyHit;
use Jogo\Domain\Value\ModifierType;
use Jogo\Domain\Value\StatModifier;
use Jogo\Domain\Weapon\Weapon;
use Jogo\Domain\Weapon\WeaponStatsMapper;
use Jogo\Tests\Support\CombatHarness;
use Jogo\Tests\Support\ManualClock;
use Jogo\Tests\Support\ProgressionFixtures;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WeaponProgressionTest extends TestCase
{
    /** @return iterable<string, array{string, array<string, float>}> */
    public static function realUpgrades(): iterable
    {
        yield 'sword' => ['stone_sword', ['dano' => 3.5, 'cooldown' => 1.7, 'alcance' => 112.5, 'anguloGraus' => 180.0]];
        yield 'bow' => ['bow', ['dano' => 4.4, 'cooldown' => 1.4400000000000002, 'alcance' => 320.0,
            'projeteis' => 2.0, 'velocidadeProjetil' => 546.0]];
        yield 'tnt' => ['tnt', ['dano' => 7.5, 'cooldown' => 3.0, 'alcance' => 260.0,
            'raioExplosao' => 143.0, 'atrasoDetonacaoSegundos' => 0.6]];
        yield 'torch' => ['torch', ['dano' => 2.028, 'cooldown' => 0.6400000000000001, 'alcance' => 100.0]];
    }

    /** @param array<string, float> $expected */
    #[DataProvider('realUpgrades')]
    public function test_all_real_upgrades_affect_the_next_attack_without_changing_the_base(string $id, array $expected): void
    {
        $h = new CombatHarness;
        $h->equip($id);
        $base = $h->catalog->get($id);
        $weapon = $h->inventory->at(0);
        self::assertNotNull($weapon);
        $service = ProgressionFixtures::service($h);
        $upgrades = ProgressionFixtures::upgrades($h->catalog);
        for ($level = 2; $level <= 5; $level++) {
            $service->applyUpgrade(0, $upgrades->get($id, $level));
        }
        foreach ($expected as $attribute => $value) {
            self::assertEqualsWithDelta($value, WeaponStatsMapper::fromDefinition($weapon->definition())->value($attribute), 1.0E-12);
        }
        self::assertSame($base, $weapon->baseDefinition());
        if ($id === 'stone_sword') {
            $h->world->add('target', 0.0, 100.0);
        } elseif ($id === 'tnt') {
            $h->world->add('target', 200.0);
            $h->world->add('boundary', 343.0);
        } else {
            $h->world->add('target', 100.0);
        }
        self::assertSame(1, $h->step(0.0, 0.0));
        if ($id === 'bow') {
            self::assertCount(2, $h->combat->projectiles());
            $h->step(0.01, 0.01);
            self::assertSame(5.46, $h->combat->projectiles()[0]->position()->x());
            $h->step(0.2, 0.21);
        } elseif ($id === 'tnt') {
            self::assertSame(0.6, $h->combat->charges()[0]->remainingSeconds());
            $h->step(0.6, 0.6);
            self::assertSame(['boundary', 'target'], array_column($h->hits, 'enemyId'));
        }
        self::assertNotEmpty($h->hits);
        foreach ($h->hits as $hit) {
            self::assertSame($id, $hit->weaponId);
            self::assertEqualsWithDelta($expected['dano'], $hit->appliedDamage, 1.0E-12);
        }
    }

    public function test_bow_in_flight_retains_its_damage_range_speed_and_id_after_upgrades(): void
    {
        $h = new CombatHarness;
        $h->world->add('target', 300.0);
        $h->equip('bow');
        $h->step(0.0, 0.0);
        $old = $h->combat->projectiles()[0];
        $definition = $old->attack->definition;
        $service = ProgressionFixtures::service($h);
        $service->apply(0, [new StatModifier('dano', ModifierType::FLAT, 5.0, 'damage'),
            new StatModifier('velocidadeProjetil', ModifierType::PERCENT, 1.0, 'speed'),
            new StatModifier('alcance', ModifierType::FLAT, 100.0, 'range'),
            new StatModifier('projeteis', ModifierType::FLAT, 1.0, 'count')]);
        self::assertSame($definition, $old->attack->definition);
        $h->step(0.4, 0.4);
        self::assertSame(168.0, $old->traveled());
        $h->step(0.4, 0.8);
        self::assertSame(3.2, $h->hits[0]->appliedDamage);
        self::assertSame('bow', $h->hits[0]->weaponId);
        $h->step(1.0, 1.8);
        self::assertCount(2, $h->combat->projectiles());
        $new = $h->combat->projectiles()[0]->attack->definition;
        self::assertSame([8.2, 420.0, 840.0, 2], [$new->damage, $new->range, $new->projectileSpeed, $new->projectileCount]);
        $h->step(0.4, 2.2);
        self::assertEqualsWithDelta(8.2, $h->hits[1]->appliedDamage, 1.0E-12);
        self::assertCount(2, $h->hits);
    }

    public function test_tnt_charge_keeps_snapshot_through_upgrade_and_evolution_and_new_events_use_mega_id(): void
    {
        $h = new CombatHarness;
        $h->world->add('target', 100.0);
        $h->equip('tnt');
        $h->step(0.0, 0.0);
        $charge = $h->combat->charges()[0];
        $base = $charge->attack->definition;
        $weapon = $h->inventory->at(0);
        self::assertNotNull($weapon);
        $h->step(0.5, 0.5);
        $service = ProgressionFixtures::service($h);
        $service->apply(0, [new StatModifier('dano', ModifierType::FLAT, 10.0, 'old-damage'),
            new StatModifier('raioExplosao', ModifierType::FLAT, 50.0, 'old-radius'),
            new StatModifier('atrasoDetonacaoSegundos', ModifierType::FLAT, 2.0, 'old-delay')]);
        $service->evolve(0, 'mega_tnt');
        self::assertSame($weapon, $h->inventory->at(0));
        self::assertSame([], $weapon->modifiers());
        self::assertSame('mega_tnt', $weapon->baseDefinition()->id);
        self::assertSame(2.8000000000000003, $weapon->remainingSeconds());
        self::assertSame($base, $charge->attack->definition);
        self::assertSame([6.0, 110.0, 1.0], [$base->damage, $base->explosionRadius, $base->detonationDelaySeconds]);
        $h->step(0.5, 1.0);
        self::assertSame('tnt', $h->hits[0]->weaponId);
        self::assertSame(6.0, $h->hits[0]->appliedDamage);
        $h->step($weapon->remainingSeconds(), 3.3);
        self::assertSame('mega_tnt', $h->combat->charges()[0]->attack->definition->id);
        self::assertSame(0.6, $h->combat->charges()[0]->remainingSeconds());
        $h->step(0.6, 3.9);
        self::assertSame('mega_tnt', $h->hits[1]->weaponId);
        self::assertSame(18.0, $h->hits[1]->appliedDamage);
    }

    public function test_enchanted_sword_preserves_slot_executor_and_ratio_and_discards_old_modifiers(): void
    {
        $h = new CombatHarness;
        $h->world->add('target', 20.0);
        $h->equip('torch');
        $h->equip('stone_sword');
        $service = ProgressionFixtures::service($h);
        $service->applyUpgrade(1, ProgressionFixtures::upgrades($h->catalog)->get('stone_sword', 2));
        $h->step(0.0, 0.0);
        $h->step(1.0, 1.0);
        $other = $h->inventory->at(0);
        $otherRemaining = $other?->remainingSeconds();
        $service->evolve(1, 'enchanted_stone_sword');
        $weapon = $h->inventory->at(1);
        self::assertNotNull($weapon);
        self::assertSame(0.7, $weapon->remainingSeconds());
        self::assertSame([], $weapon->modifiers());
        self::assertSame('enchanted_stone_sword', $weapon->definition()->id);
        self::assertSame(9.0, $weapon->definition()->damage);
        self::assertNull($h->inventory->find('stone_sword'));
        self::assertSame($weapon, $h->inventory->find('enchanted_stone_sword'));
        self::assertSame($other, $h->inventory->at(0));
        self::assertSame($otherRemaining, $other?->remainingSeconds());
        $h->step(0.7, 1.7);
        $hits = $h->hits;
        $hit = array_pop($hits);
        self::assertInstanceOf(EnemyHit::class, $hit);
        self::assertSame('enchanted_stone_sword', $hit->weaponId);
        self::assertSame(9.0, $hit->appliedDamage);
    }

    public function test_upgrade_cooldown_removal_and_pause_preserve_ratio_and_independence(): void
    {
        $h = new CombatHarness;
        $h->world->add('target', 20.0);
        $h->equip('stone_sword');
        $h->equip('torch');
        $h->step(0.0, 0.0);
        $h->step(1.0, 1.0);
        $service = ProgressionFixtures::service($h);
        $sword = $h->inventory->at(0);
        $torch = $h->inventory->at(1);
        self::assertNotNull($sword);
        self::assertNotNull($torch);
        $torchRemaining = $torch->remainingSeconds();
        $service->apply(0, [new StatModifier('cooldown', ModifierType::PERCENT, -0.5, 'fast')]);
        self::assertSame(0.5, $sword->remainingSeconds());
        self::assertSame($torchRemaining, $torch->remainingSeconds());
        $hits = count($h->hits);
        self::assertSame(0, $h->step(0.0, 1.0));
        self::assertCount($hits, $h->hits);
        $clock = new ManualClock;
        $loop = new GameLoop($clock, static function (float $dt) use ($h): void {
            $h->step($dt, 1.0 + $dt);
        }, static function (float $alpha): void {});
        $loop->pause();
        $clock->advance(100.0);
        self::assertSame(0, $loop->tick());
        self::assertSame(0.5, $sword->remainingSeconds());
        self::assertTrue($service->remove(0, 'fast'));
        self::assertSame(1.0, $sword->remainingSeconds());
        $loop->resume();
        $clock->advance(GameLoop::STEP_SECONDS);
        self::assertSame(1, $loop->tick());
        self::assertEqualsWithDelta(1.0 - GameLoop::STEP_SECONDS, $sword->remainingSeconds(), 1.0E-12);
    }

    public function test_bad_commands_leave_weapon_modifiers_and_cooldown_unchanged(): void
    {
        $h = new CombatHarness;
        $h->equip('stone_sword');
        $service = ProgressionFixtures::service($h);
        $weapon = $h->inventory->at(0);
        self::assertNotNull($weapon);
        $h->step(0.0, 0.0);
        $old = $weapon->definition();
        foreach ([static fn () => $service->evolve(0, 'missing'), static fn () => $service->evolve(0, 'mega_tnt'),
            static fn () => $service->apply(1, [new StatModifier('dano', ModifierType::FLAT, 1.0, 'absent')]),
            static fn () => $service->applyUpgrade(0, ProgressionFixtures::upgrades($h->catalog)->get('bow', 2))] as $action) {
            try {
                $action();
                self::fail('Comando inválido deveria falhar.');
            } catch (InvalidArgumentException) {
                self::assertSame($old, $weapon->definition());
                self::assertSame([], $weapon->modifiers());
                self::assertSame(2.0, $weapon->remainingSeconds());
            }
        }
    }

    public function test_evolution_cannot_duplicate_an_equipped_id(): void
    {
        $h = new CombatHarness;
        $h->equip('stone_sword');
        $h->inventory->add(ProgressionFixtures::evolutions($h->catalog)->get('enchanted_stone_sword')->definition, $h->combat);
        $service = ProgressionFixtures::service($h);
        $this->expectException(InvalidArgumentException::class);
        try {
            $service->evolve(0, 'enchanted_stone_sword');
        } finally {
            self::assertSame('stone_sword', $h->inventory->at(0)?->definition()->id);
        }
    }

    public function test_commands_from_damage_listeners_fail_until_the_combat_step_finishes(): void
    {
        $h = new CombatHarness;
        $h->world->add('target', 10.0);
        $h->equip('stone_sword');
        $service = ProgressionFixtures::service($h);
        $weapon = $h->inventory->at(0);
        self::assertNotNull($weapon);
        $h->events->subscribe(EnemyHit::class, static function () use ($service): void {
            $service->evolve(0, 'enchanted_stone_sword');
        });
        try {
            $h->step(0.0, 0.0);
            self::fail('Evolução durante dano deveria falhar.');
        } catch (LogicException) {
            self::assertSame('stone_sword', $weapon->definition()->id);
            self::assertSame(2.0, $weapon->remainingSeconds());
        }
        $service->evolve(0, 'enchanted_stone_sword');
        self::assertSame('enchanted_stone_sword', $weapon->definition()->id);
    }

    /** @return iterable<string, array{list<float>, bool}> */
    public static function schedules(): iterable
    {
        foreach ([true, false] as $evolve) {
            $pair = $evolve ? 'sword and TNT' : 'bow and torch';
            foreach ([30, 60, 144, 240] as $fps) {
                yield "$pair: $fps FPS" => [[1.0 / $fps], $evolve];
            }
            yield "$pair: irregular" => [[1.0 / 120.0, 1.0 / 40.0, 1.0 / 90.0, 1.0 / 30.0], $evolve];
        }
    }

    /** @param list<float> $frames */
    #[DataProvider('schedules')]
    public function test_upgrade_evolution_and_removal_traces_are_frame_rate_independent(array $frames, bool $evolve): void
    {
        $baseline = self::simulate([1.0 / 60.0], $evolve);
        $actual = self::simulate($frames, $evolve);
        self::assertCount(300, $actual['states']);
        self::assertSame($baseline, $actual);
        self::assertContains($evolve ? 'enchanted_stone_sword' : 'bow', array_column($actual['hits'], 'weaponId'));
        self::assertContains($evolve ? 'mega_tnt' : 'torch', array_column($actual['hits'], 'weaponId'));
    }

    /**
     * @param  list<float>  $frames
     * @return array{states: list<array<mixed>>, hits: list<array<mixed>>}
     */
    private static function simulate(array $frames, bool $evolve): array
    {
        $h = new CombatHarness;
        $h->world->add('a', 20.0, health: 1000.0);
        $h->world->add('b', 100.0, health: 1000.0);
        $weapons = $evolve ? ['stone_sword', 'tnt'] : ['bow', 'torch'];
        foreach ($weapons as $id) {
            $h->equip($id);
        }
        $service = ProgressionFixtures::service($h);
        $upgrades = ProgressionFixtures::upgrades($h->catalog);
        $clock = new ManualClock;
        $step = 0;
        $states = [];
        $loop = new GameLoop($clock, static function (float $dt) use ($h, $service, $upgrades, $weapons, $evolve, &$step, &$states): void {
            $step++;
            if ($step === 20) {
                foreach ($weapons as $slot => $id) {
                    for ($level = 2; $level <= 5; $level++) {
                        $service->applyUpgrade($slot, $upgrades->get($id, $level));
                    }
                }
            }
            if ($step === 90 && $evolve) {
                $service->evolve(0, 'enchanted_stone_sword');
                $service->evolve(1, 'mega_tnt');
            }
            if ($step === 180) {
                $service->apply(0, [new StatModifier('dano', ModifierType::PERCENT, 0.1, 'new-base')]);
            }
            if ($step === 220) {
                $service->remove(0, 'new-base');
            }
            $h->step($dt, $step * GameLoop::STEP_SECONDS);
            $states[] = [array_map(static fn (Weapon $w): array => [$w->baseDefinition()->id,
                WeaponStatsMapper::fromDefinition($w->definition())->values(), $w->remainingSeconds(),
                array_column($w->modifiers(), 'source')], $h->inventory->weapons()),
                array_map(static fn ($target): float => $target->health()->current(), $h->world->targets),
                array_map(static fn ($charge): array => [$charge->attack->id, $charge->attack->definition->id,
                    $charge->remainingSeconds(), $charge->position->x(), $charge->position->y()], $h->combat->charges()),
                array_map(static fn ($projectile): array => [$projectile->attack->id, $projectile->attack->definition->id,
                    $projectile->position()->x(), $projectile->position()->y(), $projectile->traveled()], $h->combat->projectiles())];
        }, static function (float $alpha): void {});
        $total = 0.0;
        $frame = 0;
        while ($total < 5.0) {
            $dt = min($frames[$frame % count($frames)], 5.0 - $total);
            $clock->advance($dt);
            $total += $dt;
            $loop->tick();
            $frame++;
        }

        return ['states' => $states, 'hits' => array_map(static fn (EnemyHit $hit): array => ['weaponId' => $hit->weaponId, 'id' => $hit->enemyId, 'damage' => $hit->appliedDamage,
            'remaining' => $hit->remainingHealth, 'time' => $hit->elapsedSeconds], $h->hits)];
    }
}
