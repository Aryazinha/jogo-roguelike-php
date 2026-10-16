<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression;

use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Event\EventBusInterface;
use Jogo\Domain\Event\LevelUp;
use Jogo\Domain\Random\RandomIndex;
use Jogo\Domain\Value\CombatValidation;

/** Concede XP por inimigo derrotado (RF11) e publica um LevelUp por nível alcançado. */
final class ExperienceSystem
{
    /** @var array<string, true> */
    private array $rewardedEnemies = [];

    private float $xpMultiplier = 1.0;

    public function __construct(
        private readonly string $playerId,
        private readonly Experience $experience,
        private readonly EnemyXpTable $xpTable,
        private readonly RandomIndex $random,
        private readonly EventBusInterface $events,
    ) {
        CombatValidation::requiredId($playerId, 'playerId');
    }

    public function subscribe(): void
    {
        $this->events->subscribe(EnemyKilled::class, function (EnemyKilled $event): void {
            $this->onEnemyKilled($event);
        });
    }

    /**
     * Multiplicador de XP por morte, usado pela Chuva de XP (RF35). O resultado é arredondado
     * para o inteiro mais próximo; 1.0 restaura o valor normal.
     */
    public function setXpMultiplier(float $multiplier): void
    {
        CombatValidation::nonNegative($multiplier, 'multiplicador de XP');
        $this->xpMultiplier = $multiplier;
    }

    public function xpMultiplier(): float
    {
        return $this->xpMultiplier;
    }

    public function experience(): Experience
    {
        return $this->experience;
    }

    /** Devolve o XP concedido; a mesma morte nunca é recompensada duas vezes. */
    public function onEnemyKilled(EnemyKilled $event): int
    {
        $key = 'id:'.$event->enemyId;
        if (isset($this->rewardedEnemies[$key])) {
            return 0;
        }
        $amount = (int) round($this->xpTable->get($event->enemyTypeId)->roll($this->random) * $this->xpMultiplier);
        $this->rewardedEnemies[$key] = true;
        $this->grant($amount, $event->elapsedSeconds);

        return $amount;
    }

    /**
     * Concessão direta, também usada por eventos como a Chuva de XP.
     *
     * @return list<int> Níveis alcançados.
     */
    public function grant(int $amount, float $elapsedSeconds): array
    {
        CombatValidation::nonNegative($elapsedSeconds, 'elapsedSeconds');
        $reached = $this->experience->gain($amount);
        foreach ($reached as $level) {
            $this->events->publish(new LevelUp($this->playerId, $level, $this->experience->totalXp(), $elapsedSeconds));
        }

        return $reached;
    }
}
