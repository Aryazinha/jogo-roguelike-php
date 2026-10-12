<?php

declare(strict_types=1);

namespace Jogo\Application\Benchmark;

use InvalidArgumentException;
use Jogo\Application\Simulation\MeasurementClock;
use Jogo\Application\Simulation\SimulationClock;
use Jogo\Core\EventBus;
use Jogo\Core\GameLoop;
use Jogo\Domain\Ai\SpatialWorldView;
use Jogo\Domain\Event\EnemyHit;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Domain\Spatial\SpatialHash;
use Jogo\Domain\System\CollisionSystem;
use Jogo\Domain\System\DamageSystem;
use Jogo\Domain\System\GeometryMetrics;
use Jogo\Domain\System\MovementSystem;
use Jogo\Domain\System\WeaponAttackSystem;
use Jogo\Domain\Value\InputState;
use Jogo\Domain\Value\MapBounds;
use Jogo\Domain\Value\ModifierType;
use Jogo\Domain\Value\StatModifier;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\CombatInventory;
use Jogo\Domain\Weapon\ProjectilePool;
use Jogo\Domain\Weapon\Upgrade\EvolutionCatalog;
use Jogo\Domain\Weapon\Upgrade\UpgradeCatalog;
use Jogo\Domain\Weapon\Upgrade\WeaponProgressionService;
use Jogo\Domain\Weapon\WeaponCatalog;
use Jogo\Infrastructure\Input\ScriptedInput;
use UnexpectedValueException;

/** Somente composição; movimento, mira, dano e evolução continuam nos sistemas reais. */
final readonly class BenchmarkSession
{
    public function __construct(private WeaponCatalog $weapons, private UpgradeCatalog $upgrades,
        private EvolutionCatalog $evolutions, private MeasurementClock $measurement) {}

    /** @param list<float> $frames Agendas artificiais, sem descarte de tempo para comparar FPS. */
    public function run(BenchmarkOptions $options, array $frames = [GameLoop::STEP_SECONDS], bool $captureEvents = false): BenchmarkRun
    {
        if ($frames === []) {
            throw new InvalidArgumentException('A agenda não pode ser vazia.');
        }
        foreach ($frames as $frame) {
            if (! is_finite($frame) || $frame <= 0.0 || $frame > GameLoop::STEP_SECONDS * 5.0) {
                throw new InvalidArgumentException('Intervalos de quadro devem ser positivos e caber no orçamento do loop.');
            }
        }
        $memoryStart = memory_get_usage();
        $world = new BenchmarkWorld($options->seed, $options->enemies);
        $geometry = new GeometryMetrics;
        $grid = new SpatialHash(metrics: $geometry);
        $view = new SpatialWorldView($world, fn (): Vector2 => $world->player->position(), $grid, $geometry);
        $movement = new MovementSystem(new MapBounds(-320.0, -256.0, 320.0, 256.0));
        $collision = new CollisionSystem($geometry);
        $bus = new EventBus;
        $pendingEvents = [];
        $impacts = $deaths = 0;
        $bus->subscribe(EnemyHit::class, static function (EnemyHit $event) use (&$pendingEvents, &$impacts, $view): void {
            $view->invalidate();
            $pendingEvents[] = $event;
            $impacts++;
        });
        $bus->subscribe(EnemyKilled::class, static function (EnemyKilled $event) use (&$pendingEvents, &$deaths, $view): void {
            $view->invalidate();
            $pendingEvents[] = $event;
            $deaths++;
        });
        $combat = new WeaponAttackSystem($world->player->id(), $world, $view, $view, $world,
            new DamageSystem($bus), new SeededRandomIndex($options->seed), new ProjectilePool, $geometry);
        // Quatro slots somente no ensaio; o inventário de gameplay continua com padrão 2.
        $inventory = new CombatInventory(4);
        foreach (['stone_sword', 'bow', 'tnt', 'torch'] as $id) {
            $inventory->add($this->weapons->get($id), $combat);
        }
        $progression = new WeaponProgressionService($inventory, $this->evolutions, $combat);
        $progression->apply(1, [new StatModifier('projeteis', ModifierType::FLAT, $options->projectiles - 1.0, 'a10:count'),
            new StatModifier('cooldown', ModifierType::PERCENT, 0.02 / $this->weapons->get('bow')->cooldownSeconds - 1.0, 'a10:cadence')]);
        $progression->apply(2, [new StatModifier('cooldown', ModifierType::PERCENT, -0.9, 'a10:tnt-cadence')]);
        $input = new ScriptedInput(self::inputs($options->warmup + $options->steps));
        $clock = new SimulationClock;
        $step = $accepted = $contacts = $peakProjectiles = 0;
        $minimumEnemies = $minimumEntities = PHP_INT_MAX;
        $maximumEnemies = 0;
        $durations = $geometrySamples = $entityCounts = $eventValues = [];
        $eventHash = hash_init('sha256');
        $loop = new GameLoop($clock, function (float $dt) use ($options, $world, $movement, $input, $geometry, $grid, $view, $collision,
            $combat, $inventory, $progression, &$step, &$accepted, &$contacts, &$durations, &$geometrySamples, &$entityCounts,
            &$minimumEnemies, &$minimumEntities, &$maximumEnemies, &$peakProjectiles, &$pendingEvents, &$eventValues, $eventHash, $captureEvents): void {
            if ($step >= $options->warmup + $options->steps) {
                return;
            }
            $step++;
            $measured = $step > $options->warmup;
            $start = $measured ? $this->measurement->nowNanoseconds() : null;
            $geometry->beginStep();
            $movement->update($world->player, $world->player->facing, $input->poll(), 240.0, $dt, $world->player->isActive());
            $world->moveEnemies($movement, $dt, $step);
            if ($options->freshQueries) {
                $view->invalidate();
                $grid->rebuild($world->collidables());
            } else {
                $view->refresh();
            }
            foreach ($grid->queryRadius($world->player->position(), $world->player->collisionRadius(), $world->player->id())->entities as $other) {
                // Exercita contato sem inventar dano por contato ou resposta física.
                if ($collision->intersects($world->player, $other)) {
                    $contacts++;
                }
            }
            if ($step === 30) {
                $progression->apply(1, [new StatModifier('dano', ModifierType::FLAT, 1.0, 'a10:flat')]);
            }
            if ($step === 45) {
                $progression->applyUpgrade(0, $this->upgrades->get('stone_sword', 2));
            }
            if ($step === 60) {
                $progression->apply(1, [new StatModifier('dano', ModifierType::PERCENT, 0.1, 'a10:percent')]);
            }
            if ($step === 90) {
                $progression->remove(1, 'a10:flat');
            }
            if ($step === 120) {
                $progression->evolve(0, 'enchanted_stone_sword');
            }
            if ($step === 150) {
                $progression->evolve(2, 'mega_tnt');
            }
            $accepted += $combat->update($inventory, $dt, $step * GameLoop::STEP_SECONDS);
            if ($start !== null) {
                $duration = $this->measurement->nowNanoseconds() - $start;
                if ($duration < 0) {
                    throw new UnexpectedValueException('O relógio de medição retrocedeu.');
                }
                $durations[] = $duration;
                $enemies = $world->activeEnemies();
                $entities = $enemies + ($world->player->isActive() ? 1 : 0);
                $geometrySamples[] = $geometry->snapshot();
                $entityCounts[] = $entities;
                $minimumEnemies = min($minimumEnemies, $enemies);
                $minimumEntities = min($minimumEntities, $entities);
                $maximumEnemies = max($maximumEnemies, $enemies);
                $peakProjectiles = max($peakProjectiles, $combat->poolSnapshot()->active);
            }
            // Serialização/checksum ficam fora do intervalo medido.
            foreach ($pendingEvents as $event) {
                $values = $event instanceof EnemyHit
                    ? ['hit', $event->enemyId, $event->weaponId, $event->position->x(), $event->position->y(), $event->appliedDamage, $event->remainingHealth, $event->elapsedSeconds]
                    : ['death', $event->enemyId, $event->weaponId, $event->position->x(), $event->position->y(), $event->elapsedSeconds];
                hash_update($eventHash, json_encode($values, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION)."\n");
                if ($captureEvents) {
                    $eventValues[] = $values;
                }
            }
            $pendingEvents = [];
        }, static function (float $alpha): void {});
        try {
            $frame = 0;
            while ($step < $options->warmup + $options->steps) {
                $clock->advance(min($frames[$frame++ % count($frames)], ($options->warmup + $options->steps - $step) * GameLoop::STEP_SECONDS));
                $loop->tick();
            }
            $pool = $combat->poolSnapshot();
            $state = ['player' => [$world->player->position()->x(), $world->player->position()->y(), $world->player->health()->current(),
                $world->player->facing->direction()->x(), $world->player->facing->direction()->y()], 'enemies' => $world->state(),
                'weapons' => array_map(static fn ($w): array => [$w->definition(), $w->remainingSeconds()], $inventory->weapons()),
                'projectiles' => array_map(static fn ($p): array => [$p->attack->id, $p->attack->definition, $p->origin->x(), $p->origin->y(),
                    $p->target->entityId, $p->target->position->x(), $p->target->position->y(), $p->target->direction->x(), $p->target->direction->y(),
                    $p->position()->x(), $p->position()->y(), $p->traveled()], $combat->projectiles()),
                'charges' => array_map(static fn ($c): array => [$c->attack->id, $c->attack->definition, $c->position->x(), $c->position->y(), $c->remainingSeconds()], $combat->charges())];
        } finally {
            $combat->clear();
            $view->invalidate();
            $grid->clear();
            $world->clear();
        }
        $logical = new BenchmarkLogical($options->warmup, $options->steps, $minimumEnemies, $maximumEnemies, $minimumEntities,
            $peakProjectiles, $accepted, $impacts, $deaths, $contacts, $pool, $combat->poolSnapshot());
        $eventsChecksum = hash_final($eventHash);
        // Contadores operacionais (consultas/reconstruções) são comparados à parte em otimizações.
        $checksum = hash('sha256', json_encode([$options->seed, $state, $eventsChecksum, $logical], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

        return new BenchmarkRun($durations, new CollisionSummary($geometrySamples, $entityCounts, $options->warmup + 1),
            $logical, $checksum, $eventsChecksum, $state, $eventValues, $memoryStart, memory_get_peak_usage());
    }

    /** @return list<InputState> Roteiro lógico; não é amostragem de hardware por quadro. */
    private static function inputs(int $steps): array
    {
        $directions = [[1.0, 0.0], [1.0, 1.0], [0.0, 1.0], [-1.0, 0.0], [-1.0, -1.0], [0.0, -1.0], [1.0, -1.0], [0.0, 0.0]];
        $states = [];
        foreach ($directions as [$x, $y]) {
            $movement = new Vector2($x, $y);
            $states[] = new InputState($movement, $movement->length() === 0.0 ? null : $movement->normalized());
        }
        $script = [];
        for ($step = 0; $step < $steps; $step++) {
            $script[] = $states[intdiv($step, 60) % count($states)];
        }

        return $script;
    }
}
