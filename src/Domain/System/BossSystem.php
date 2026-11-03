<?php

declare(strict_types=1);

namespace Jogo\Domain\System;

use Jogo\Domain\Enemy\Boss\BossPhase;
use Jogo\Domain\Enemy\Boss\BossPhaseSettings;
use Jogo\Domain\Enemy\Boss\WardenBrain;
use Jogo\Domain\Enemy\Enemy;
use Jogo\Domain\Enemy\EnemyFactory;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Event\EventBusInterface;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\InputState;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\World\GameWorld;
use Jogo\Domain\World\SpawnPointPicker;

/**
 * Coloca o Warden na partida e executa o que o WardenBrain decide (RF22-b).
 *
 * - surge no horário do arquétipo (600 s), num ponto de spawn comum;
 * - anda pelo MovementSystem da A, com a velocidade multiplicada na
 *   investida e na fúria, por isso não passa pelo EnemySteeringSystem;
 * - as invocações vão para a fila do mundo, como os filhos do Slime;
 * - a derrota é o EnemyKilled com isBoss = true, que já existe na lista
 *   fechada de eventos; aqui ela só fica registrada para a vitória (RF09).
 */
final class BossSystem
{
    /** Distância extra, além do raio do boss, em que os invocados aparecem. */
    private const SUMMON_GAP = 30.0;

    private ?Enemy $boss = null;

    private ?WardenBrain $brain = null;

    private ?float $defeatedAt = null;

    public function __construct(
        private readonly GameWorld $world,
        private readonly EnemyFactory $factory,
        private readonly BossPhaseSettings $settings,
        private readonly SpawnPointPicker $spawns,
        private readonly MovementSystem $movement,
        private readonly string $bossTypeId,
        private readonly float $spawnAtSeconds,
    ) {
        CombatValidation::requiredId($bossTypeId, 'bossTypeId');
        CombatValidation::nonNegative($spawnAtSeconds, 'spawnAtSeconds');
    }

    public function subscribe(EventBusInterface $events): void
    {
        $events->subscribe(EnemyKilled::class, $this->onEnemyKilled(...));
    }

    public function onEnemyKilled(EnemyKilled $event): void
    {
        if ($event->isBoss && $this->boss !== null && $event->enemyId === $this->boss->id() && $this->defeatedAt === null) {
            $this->defeatedAt = $event->elapsedSeconds;
        }
    }

    public function update(float $deltaSeconds, float $elapsedSeconds): void
    {
        CombatValidation::nonNegative($deltaSeconds, 'deltaSeconds');
        CombatValidation::nonNegative($elapsedSeconds, 'elapsedSeconds');

        if ($this->boss === null && $elapsedSeconds >= $this->spawnAtSeconds) {
            $this->spawn();
        }
        $boss = $this->boss;
        if ($boss === null || $this->brain === null || ! $boss->isActive() || $boss->health()->isDead()) {
            return;
        }

        $toPlayer = $this->world->player()->position()->subtract($boss->position());
        $action = $this->brain->update($deltaSeconds, $boss->health()->ratio(), $toPlayer);

        if ($action->summon > 0) {
            $this->summon($boss, $action->summon);
        }
        if ($action->speedMultiplier > 0.0 && $action->direction->length() > 0.0) {
            $this->movement->update($boss, $boss->facing(), new InputState($action->direction, $action->direction->normalized()),
                $boss->speed() * $action->speedMultiplier, $deltaSeconds, true);
        }
    }

    public function boss(): ?Enemy
    {
        return $this->boss;
    }

    /** Fase atual, para a Frente D piscar no telegrafar, por exemplo. */
    public function phase(): ?BossPhase
    {
        return $this->brain?->phase();
    }

    public function isEnraged(): bool
    {
        return $this->brain?->isEnraged() ?? false;
    }

    public function isDefeated(): bool
    {
        return $this->defeatedAt !== null;
    }

    public function defeatedAt(): ?float
    {
        return $this->defeatedAt;
    }

    private function spawn(): void
    {
        $boss = $this->factory->create($this->bossTypeId, $this->spawns->pick($this->world->player()->position()));
        $this->world->addEnemy($boss);
        $this->boss = $boss;
        $this->brain = new WardenBrain($this->settings);
    }

    /** Invocados em círculo em volta do boss, dentro do mapa. */
    private function summon(Enemy $boss, int $count): void
    {
        $distance = $boss->collisionRadius() + self::SUMMON_GAP;
        $bounds = $this->world->layout()->bounds();
        // Respeita o teto de inimigos; o boss em si sempre nasce, mas os invocados só se couberem.
        $count = min($count, $this->world->freeEnemySlots());
        for ($i = 0; $i < $count; $i++) {
            $angle = 2.0 * M_PI * $i / $count;
            $offset = new Vector2(cos($angle) * $distance, sin($angle) * $distance);
            $this->world->queueEnemy($this->factory->create($this->settings->summonTypeId, $bounds->clamp($boss->position()->add($offset))));
        }
    }
}
