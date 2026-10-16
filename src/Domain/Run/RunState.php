<?php

declare(strict_types=1);

namespace Jogo\Domain\Run;

use Jogo\Domain\Event\EnemyKilled;
use Jogo\Domain\Event\EventBusInterface;
use Jogo\Domain\Event\PlayerDied;
use Jogo\Domain\Progression\Experience;
use Jogo\Domain\Value\CombatValidation;
use LogicException;

/**
 * Estado da partida para resumo e pontuação. O tempo só avança nos passos fixos, então a
 * pausa fica fora da conta (RF30). Derrotar o boss marca vitória e a partida continua em
 * modo livre (PLANO.md §1.3); ela termina somente na morte do jogador, uma única vez.
 */
final class RunState
{
    private float $survivalSeconds = 0.0;

    private float $correction = 0.0;

    private int $enemiesKilled = 0;

    /** @var array<string, true> */
    private array $countedEnemies = [];

    private bool $won = false;

    private bool $ended = false;

    public function __construct(
        private readonly string $playerId,
        private readonly string $characterId,
        private readonly int $seed,
        private readonly Experience $experience,
    ) {
        CombatValidation::requiredId($playerId, 'playerId');
        CombatValidation::requiredId($characterId, 'characterId');
    }

    public function subscribe(EventBusInterface $events): void
    {
        $events->subscribe(EnemyKilled::class, function (EnemyKilled $event): void {
            $this->recordKill($event);
        });
        $events->subscribe(PlayerDied::class, function (PlayerDied $event): void {
            if ($event->playerId === $this->playerId) {
                $this->end();
            }
        });
    }

    /** Chamado a cada passo fixo; depois do fim não altera mais nada. */
    public function advance(float $deltaSeconds): void
    {
        CombatValidation::nonNegative($deltaSeconds, 'deltaSeconds');
        if ($this->ended) {
            return;
        }
        $adjusted = $deltaSeconds - $this->correction;
        $next = $this->survivalSeconds + $adjusted;
        $this->correction = ($next - $this->survivalSeconds) - $adjusted;
        $this->survivalSeconds = $next;
    }

    public function recordKill(EnemyKilled $event): bool
    {
        $key = 'id:'.$event->enemyId;
        if ($this->ended || isset($this->countedEnemies[$key])) {
            return false;
        }
        $this->countedEnemies[$key] = true;
        $this->enemiesKilled++;
        if ($event->isBoss) {
            $this->won = true;
        }

        return true;
    }

    /** Vitória informada diretamente pela Frente C, quando não vier de EnemyKilled. */
    public function recordVictory(): void
    {
        if (! $this->ended) {
            $this->won = true;
        }
    }

    /** Encerra a partida; chamadas repetidas não mudam o resumo. */
    public function end(): bool
    {
        if ($this->ended) {
            return false;
        }
        $this->ended = true;

        return true;
    }

    public function isEnded(): bool
    {
        return $this->ended;
    }

    public function hasWon(): bool
    {
        return $this->won;
    }

    public function survivalSeconds(): float
    {
        return $this->survivalSeconds;
    }

    public function enemiesKilled(): int
    {
        return $this->enemiesKilled;
    }

    public function summary(): RunSummary
    {
        if (! $this->ended) {
            throw new LogicException('O resumo só existe depois do fim da partida.');
        }

        return new RunSummary($this->characterId, $this->experience->level(), $this->enemiesKilled,
            $this->survivalSeconds, $this->won, $this->seed);
    }
}
