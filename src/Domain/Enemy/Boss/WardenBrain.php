<?php

declare(strict_types=1);

namespace Jogo\Domain\Enemy\Boss;

use Jogo\Domain\Value\CombatValidation;
use Jogo\Domain\Value\Vector2;

/**
 * Máquina de estados do Warden (RF22-b, PLANO.md §4.2).
 *
 *   perseguindo ──(cooldown de 8 s acabou)──> telegrafando (0,8 s, parado, direção travada)
 *        ^                                         │
 *        │                                         v
 *   recuperando (1 s, parado) <──(1 s)── investindo (3× a velocidade, em linha reta)
 *
 *   perseguindo ──(vida cruzou 70% ou 40%)──> invocando (1 s, parado) ──> perseguindo
 *
 * Abaixo de 25% de vida fica enfurecido até o fim: anda 30% mais rápido e
 * espera 30% menos entre investidas.
 *
 * Não conhece o mundo: recebe a vida e a direção do jogador e devolve uma
 * BossAction. Por isso dá para testar cada transição sem montar uma partida.
 */
final class WardenBrain
{
    /** Tolerância para o acúmulo de 1/60 em float não atrasar uma fase em um passo. */
    private const EPSILON = 1e-9;

    private BossPhase $phase = BossPhase::CHASING;

    private float $phaseRemaining = 0.0;

    private float $chargeCooldown;

    private Vector2 $lockedDirection;

    /** Próximo limiar de invocação ainda não cruzado. */
    private int $nextThreshold = 0;

    /** Invocações devidas: um golpe grande pode cruzar dois limiares de uma vez. */
    private int $pendingSummons = 0;

    private bool $enraged = false;

    public function __construct(private readonly BossPhaseSettings $settings)
    {
        $this->chargeCooldown = $settings->chargeCooldownSeconds;
        $this->lockedDirection = new Vector2(1.0, 0.0);
    }

    public function phase(): BossPhase
    {
        return $this->phase;
    }

    public function isEnraged(): bool
    {
        return $this->enraged;
    }

    /** Direção da investida, fixada no começo do telegrafar. */
    public function lockedDirection(): Vector2
    {
        return $this->lockedDirection;
    }

    public function update(float $deltaSeconds, float $healthRatio, Vector2 $toPlayer): BossAction
    {
        CombatValidation::nonNegative($deltaSeconds, 'deltaSeconds');
        // Pausa: nada avança, nem os relógios das fases.
        if ($deltaSeconds === 0.0) {
            return BossAction::standStill();
        }
        $this->checkHealth($healthRatio);
        $speed = $this->enraged ? 1.0 + $this->settings->enragedSpeedBonus : 1.0;

        switch ($this->phase) {
            case BossPhase::CHASING:
                if ($this->pendingSummons > 0) {
                    return $this->startSummoning();
                }
                $this->chargeCooldown -= $deltaSeconds;
                if ($this->chargeCooldown <= self::EPSILON) {
                    $direction = $toPlayer->normalized();
                    $this->lockedDirection = $direction->length() > 0.0 ? $direction : $this->lockedDirection;
                    $this->enter(BossPhase::TELEGRAPHING, $this->settings->telegraphSeconds);

                    return BossAction::standStill();
                }

                return new BossAction($toPlayer->normalized(), $speed);

            case BossPhase::TELEGRAPHING:
                if ($this->tick($deltaSeconds)) {
                    $this->enter(BossPhase::CHARGING, $this->settings->chargeSeconds);
                }

                return BossAction::standStill();

            case BossPhase::CHARGING:
                // Anda neste passo mesmo se for o último da investida.
                $action = new BossAction($this->lockedDirection, $speed * $this->settings->chargeSpeedMultiplier);
                if ($this->tick($deltaSeconds)) {
                    $this->enter(BossPhase::RECOVERING, $this->settings->recoverSeconds);
                }

                return $action;

            case BossPhase::RECOVERING:
                if ($this->tick($deltaSeconds)) {
                    $this->backToChasing();
                }

                return BossAction::standStill();

            case BossPhase::SUMMONING:
                if ($this->tick($deltaSeconds)) {
                    if ($this->pendingSummons > 0) {
                        return $this->startSummoning();
                    }
                    $this->phase = BossPhase::CHASING;
                }

                return BossAction::standStill();
        }
    }

    private function checkHealth(float $ratio): void
    {
        $thresholds = $this->settings->summonThresholds;
        while ($this->nextThreshold < count($thresholds) && $ratio <= $thresholds[$this->nextThreshold]) {
            $this->pendingSummons++;
            $this->nextThreshold++;
        }
        if (! $this->enraged && $ratio <= $this->settings->enrageThreshold) {
            $this->enraged = true;
        }
    }

    private function startSummoning(): BossAction
    {
        $this->pendingSummons--;
        $this->enter(BossPhase::SUMMONING, $this->settings->summonSeconds);

        return BossAction::standStill($this->settings->summonCount);
    }

    private function backToChasing(): void
    {
        $this->phase = BossPhase::CHASING;
        $factor = $this->enraged ? 1.0 + $this->settings->enragedCooldownBonus : 1.0;
        $this->chargeCooldown = $this->settings->chargeCooldownSeconds * $factor;
    }

    private function enter(BossPhase $phase, float $seconds): void
    {
        $this->phase = $phase;
        $this->phaseRemaining = $seconds;
    }

    /** Desconta o passo da fase atual; true quando a fase terminou. */
    private function tick(float $deltaSeconds): bool
    {
        $this->phaseRemaining -= $deltaSeconds;

        return $this->phaseRemaining <= self::EPSILON;
    }
}
