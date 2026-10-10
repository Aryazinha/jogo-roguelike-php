<?php

declare(strict_types=1);

namespace Jogo\Domain\System;

use Jogo\Domain\Entity\Damageable;
use Jogo\Domain\Entity\Entity;
use Jogo\Domain\Event\DomainEvent;
use Jogo\Domain\Event\EnemyHit;
use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Event\EventBusInterface;
use Jogo\Domain\Event\PlayerDamaged;
use Jogo\Domain\Event\PlayerDied;
use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Damage;
use Jogo\Domain\Value\Vector2;

/** Aplica ataques já resolvidos; uma única instância compartilhada por partida. */
final class DamageSystem
{
    /** @var array<string, true> IDs terminais desta partida, sem referências às entidades. */
    private array $processedDeaths = [];

    public function __construct(private readonly EventBusInterface $events) {}

    public function damageEnemy(Entity&Damageable $target, Damage $damage, string $enemyTypeId, bool $isBoss, float $elapsedSeconds): float
    {
        CombatValidation::requiredId($enemyTypeId, 'enemyTypeId');
        $result = $this->applyDamage($target, $damage, $elapsedSeconds);

        if ($result === null) {
            return 0.0;
        }

        $hit = new EnemyHit($target->id(), $enemyTypeId, $result['position'], $result['applied'],
            $result['remaining'], $damage->sourceEntityId, $damage->weaponId, $elapsedSeconds);
        $death = $result['remaining'] === 0.0
            ? new EnemyKilled($target->id(), $enemyTypeId, $isBoss, $result['position'],
                $damage->sourceEntityId, $damage->weaponId, $elapsedSeconds)
            : null;
        $this->publishDamage($target, $hit, $death);

        return $result['applied'];
    }

    public function damagePlayer(Entity&Damageable $target, Damage $damage, float $elapsedSeconds): float
    {
        $result = $this->applyDamage($target, $damage, $elapsedSeconds);

        if ($result === null) {
            return 0.0;
        }

        $hit = new PlayerDamaged($target->id(), $result['position'], $result['applied'],
            $result['remaining'], $damage->sourceEntityId, $elapsedSeconds);
        $death = $result['remaining'] === 0.0
            ? new PlayerDied($target->id(), $result['position'], $damage->sourceEntityId, $elapsedSeconds)
            : null;
        $this->publishDamage($target, $hit, $death);

        return $result['applied'];
    }

    /** @return array{applied: float, remaining: float, position: Vector2}|null */
    private function applyDamage(Entity&Damageable $target, Damage $damage, float $elapsedSeconds): ?array
    {
        // Validação antes de qualquer mutação, inclusive para chamadas que seriam ignoradas.
        CombatValidation::nonNegative($elapsedSeconds, 'elapsedSeconds');
        $key = 'id:'.$target->id();

        if (isset($this->processedDeaths[$key]) || ! $target->isActive()) {
            return null;
        }

        // Uma leitura garante o uso do mesmo componente de vida durante toda a aplicação.
        $health = $target->health();

        if ($health->isDead()) {
            $this->processedDeaths[$key] = true;
            $target->deactivate();

            return null;
        }

        $applied = $health->damage($damage->amount);

        if ($applied === 0.0) {
            return null;
        }

        $position = $target->position();
        $remaining = $health->current();

        if ($remaining === 0.0) {
            // Reserva a morte antes dos listeners; nem outra instância com este ID a repete.
            $this->processedDeaths[$key] = true;
        }

        return ['applied' => $applied, 'remaining' => $remaining,
            'position' => new Vector2($position->x(), $position->y())];
    }

    private function publishDamage(Entity&Damageable $target, DomainEvent $hit, ?DomainEvent $death): void
    {
        try {
            $this->events->publish($hit);
        } finally {
            // Mesmo se um listener falhar, um golpe fatal não deixa o alvo ativo com HP zero.
            if ($death !== null) {
                $target->deactivate();
            }
        }

        if ($death !== null) {
            $this->events->publish($death);
        }
    }
}
