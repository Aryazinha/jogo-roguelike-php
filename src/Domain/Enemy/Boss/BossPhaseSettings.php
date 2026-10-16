<?php

declare(strict_types=1);

namespace Jogo\Domain\Enemy\Boss;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;

/**
 * Durações, limiares e multiplicadores das fases do Warden (PLANO.md §4.2).
 *
 * Os limiares são frações da vida máxima: 0.7 quer dizer 70% da vida.
 */
final readonly class BossPhaseSettings
{
    /** @var list<float> Limiares de invocação, do maior para o menor. */
    public array $summonThresholds;

    /** @param list<float> $summonThresholds */
    public function __construct(
        public float $telegraphSeconds,
        public float $chargeSeconds,
        public float $chargeSpeedMultiplier,
        public float $recoverSeconds,
        public float $chargeCooldownSeconds,
        array $summonThresholds,
        public int $summonCount,
        public string $summonTypeId,
        public float $summonSeconds,
        public float $enrageThreshold,
        public float $enragedSpeedBonus,
        public float $enragedCooldownBonus,
    ) {
        CombatValidation::positive($telegraphSeconds, 'telegraphSeconds');
        CombatValidation::positive($chargeSeconds, 'chargeSeconds');
        CombatValidation::positive($chargeSpeedMultiplier, 'chargeSpeedMultiplier');
        CombatValidation::positive($recoverSeconds, 'recoverSeconds');
        CombatValidation::positive($chargeCooldownSeconds, 'chargeCooldownSeconds');
        CombatValidation::positive($summonSeconds, 'summonSeconds');
        CombatValidation::requiredId($summonTypeId, 'summonTypeId');
        if ($summonCount < 1) {
            throw new InvalidArgumentException('summonCount deve ser maior ou igual a um.');
        }
        foreach ([...$summonThresholds, $enrageThreshold] as $threshold) {
            if (! is_finite($threshold) || $threshold <= 0.0 || $threshold >= 1.0) {
                throw new InvalidArgumentException('limiares de vida devem estar entre 0 e 1, sem incluir os extremos.');
            }
        }
        if (count(array_unique(array_map('strval', $summonThresholds))) !== count($summonThresholds)) {
            throw new InvalidArgumentException('summonThresholds não pode repetir limiar.');
        }
        // Bônus de velocidade soma; bônus de cooldown reduz: -0.3 deixa a espera em 70%.
        CombatValidation::nonNegative($enragedSpeedBonus, 'enragedSpeedBonus');
        if (! is_finite($enragedCooldownBonus) || $enragedCooldownBonus <= -1.0 || $enragedCooldownBonus > 0.0) {
            throw new InvalidArgumentException('enragedCooldownBonus deve estar entre -1 (sem incluir) e 0.');
        }

        rsort($summonThresholds);
        $this->summonThresholds = $summonThresholds;
    }
}
