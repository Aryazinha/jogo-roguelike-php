<?php

declare(strict_types=1);

namespace Jogo\Domain\Character;

use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Health;

/**
 * Cura periódica pelo tempo lógico (RF16, RF17), independente da taxa de quadros.
 *
 * O relógio só corre enquanto a vida está abaixo do máximo: ao tomar dano com a vida
 * cheia, o intervalo inteiro precisa passar antes da próxima cura. Entidade morta não regenera.
 */
final class HealthRegeneration
{
    private float $elapsedSeconds = 0.0;

    private float $correction = 0.0;

    public function __construct(private readonly float $amount, private readonly float $intervalSeconds)
    {
        CombatValidation::positive($amount, 'regeneracao.hp');
        CombatValidation::positive($intervalSeconds, 'regeneracao.intervaloSegundos');
    }

    public function amount(): float
    {
        return $this->amount;
    }

    public function intervalSeconds(): float
    {
        return $this->intervalSeconds;
    }

    public function elapsedSeconds(): float
    {
        return $this->elapsedSeconds;
    }

    /** Avança o relógio e devolve o HP efetivamente restaurado neste passo. */
    public function advance(Health $health, float $deltaSeconds): float
    {
        CombatValidation::nonNegative($deltaSeconds, 'deltaSeconds');
        if ($health->isDead() || $health->current() >= $health->maximum()) {
            $this->restart();

            return 0.0;
        }
        $adjusted = $deltaSeconds - $this->correction;
        $next = $this->elapsedSeconds + $adjusted;
        $this->correction = ($next - $this->elapsedSeconds) - $adjusted;
        $this->elapsedSeconds = $next;

        $restored = 0.0;
        while ($this->elapsedSeconds >= $this->intervalSeconds) {
            $restored += $health->heal($this->amount);
            if ($health->current() >= $health->maximum()) {
                $this->restart();
                break;
            }
            $this->elapsedSeconds -= $this->intervalSeconds;
            $this->correction = 0.0;
        }

        return $restored;
    }

    private function restart(): void
    {
        $this->elapsedSeconds = 0.0;
        $this->correction = 0.0;
    }
}
