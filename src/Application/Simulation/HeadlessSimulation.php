<?php

declare(strict_types=1);

namespace Jogo\Application\Simulation;

use InvalidArgumentException;
use Jogo\Core\EventBus;
use Jogo\Core\GameLoop;
use Jogo\Domain\Ai\SpatialWorldView;
use Jogo\Domain\Event\EnemyHit;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Random\SeededRandomIndex;
use Jogo\Domain\Spatial\SpatialHash;
use Jogo\Domain\System\DamageSystem;
use Jogo\Domain\System\WeaponAttackSystem;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\CombatInventory;
use Jogo\Domain\Weapon\WeaponCatalog;
use Jogo\Infrastructure\Input\InputInterface;
use UnexpectedValueException;

final readonly class HeadlessSimulation
{
    public function __construct(private WeaponCatalog $weapons, private InputInterface $input, private MeasurementClock $measurement) {}

    /** @param list<float> $frames Intervalos artificiais de quadros, em segundos. */
    public function run(SimulationOptions $options, array $frames = [GameLoop::STEP_SECONDS]): SimulationReport
    {
        if ($frames === []) {
            throw new InvalidArgumentException('Forneça uma lista não vazia de intervalos de quadros.');
        }
        foreach ($frames as $frame) {
            if (! is_finite($frame) || $frame <= 0.0) {
                throw new InvalidArgumentException('Intervalo de quadro deve ser positivo e finito.');
            }
        }
        $world = new SyntheticWorld($options->seed, $options->enemies);
        $grid = new SpatialHash;
        $view = new SpatialWorldView($world, static fn (): Vector2 => Vector2::zero(), $grid);
        $events = new EventBus;
        $impacts = 0;
        $deaths = 0;
        $eventState = [];
        $events->subscribe(EnemyHit::class, static function (EnemyHit $hit) use (&$impacts, &$eventState): void {
            $impacts++;
            $eventState[] = ['hit', $hit->enemyId, $hit->weaponId, $hit->appliedDamage, $hit->remainingHealth, $hit->elapsedSeconds];
        });
        $events->subscribe(EnemyKilled::class, static function (EnemyKilled $death) use (&$deaths, &$eventState): void {
            $deaths++;
            $eventState[] = ['death', $death->enemyId, $death->weaponId, $death->elapsedSeconds];
        });
        $combat = new WeaponAttackSystem('benchmark-source', $world, $view, $view, $world,
            new DamageSystem($events), new SeededRandomIndex($options->seed));
        $inventory = new CombatInventory;
        $inventory->add($this->weapons->get('bow'), $combat);
        $inventory->add($this->weapons->get('tnt'), $combat);
        $clock = new SimulationClock;
        $steps = 0;
        $accepted = 0;
        $totalTime = 0;
        $maxTime = 0;
        $loop = new GameLoop($clock, function (float $dt) use ($options, $combat, $inventory, &$steps, &$accepted, &$totalTime, &$maxTime): void {
            if ($steps >= $options->steps) {
                return;
            }
            $start = $this->measurement->nowNanoseconds();
            $steps++;
            $accepted += $combat->update($inventory, $dt, $steps * GameLoop::STEP_SECONDS);
            $duration = $this->measurement->nowNanoseconds() - $start;
            if ($duration < 0) {
                throw new UnexpectedValueException('O relógio de medição retrocedeu.');
            }
            $totalTime += $duration;
            $maxTime = max($maxTime, $duration);
        }, static function (float $alpha): void {});
        try {
            $frameIndex = 0;
            while ($steps < $options->steps) {
                $input = $this->input->poll();
                if ($input->movement->length() !== 0.0 || $input->facing !== null || $input->pauseRequested) {
                    throw new InvalidArgumentException('A simulação A9 exige entrada neutra.');
                }
                $clock->advance(min($frames[$frameIndex++ % count($frames)], ($options->steps - $steps) * GameLoop::STEP_SECONDS));
                $loop->tick();
            }
            $pool = $combat->poolSnapshot();
            $collisions = $combat->collisionSnapshot();
            $entities = $world->activeCount();
            $state = ['seed' => $options->seed, 'steps' => $steps, 'entities' => $world->state(), 'events' => $eventState,
                'weapons' => array_map(static fn ($weapon): array => [$weapon->definition(), $weapon->remainingSeconds()], $inventory->weapons()),
                'projectiles' => array_map(static fn ($p): array => [$p->attack->id, $p->attack->definition, $p->origin->x(), $p->origin->y(),
                    $p->target->entityId, $p->target->direction->x(), $p->target->direction->y(), $p->position()->x(), $p->position()->y(), $p->traveled()], $combat->projectiles()),
                'charges' => array_map(static fn ($c): array => [$c->attack->id, $c->attack->definition, $c->position->x(), $c->position->y(), $c->remainingSeconds()], $combat->charges())];
        } finally {
            $combat->clear();
            $grid->clear();
            $world->clear();
        }
        $metrics = new SimulationMetrics($steps, $steps * GameLoop::STEP_SECONDS, $entities, $options->enemies,
            $pool, $combat->poolSnapshot(), $accepted, $impacts, $deaths, $collisions);
        $checksum = hash('sha256', json_encode([$state, $metrics], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));

        return new SimulationReport($options, $metrics, new UpdateTiming($steps, $totalTime, $maxTime), $checksum);
    }
}
