<?php

declare(strict_types=1);

namespace Jogo\Domain\Chest;

use InvalidArgumentException;
use Jogo\Domain\Random\RandomIndex;
use Jogo\Domain\Value\CombatValidation;

/**
 * Regras de chests.json: raridades, quantos baús o boss deixa, a chance por
 * inimigo abatido e o item de cura.
 *
 * As probabilidades precisam somar 1 e ter no máximo 4 casas decimais, para o
 * sorteio ser feito com números inteiros (de 0 a 9999), sem erro de arredondamento.
 */
final readonly class ChestSettings
{
    private const SCALE = 10_000;

    /** @var list<ChestRarity> */
    public array $rarities;

    /** @param list<ChestRarity> $rarities */
    public function __construct(
        array $rarities,
        public int $chestsOnBossDefeat,
        public float $chancePerKill,
        public HealingItem $healing,
    ) {
        if ($rarities === []) {
            throw new InvalidArgumentException('É preciso pelo menos uma raridade de baú.');
        }
        $ids = [];
        $total = 0;
        foreach ($rarities as $rarity) {
            if (isset($ids[$rarity->id])) {
                throw new InvalidArgumentException("raridade repetida: {$rarity->id}.");
            }
            $ids[$rarity->id] = true;
            $scaled = $rarity->probability * self::SCALE;
            if (abs($scaled - round($scaled)) > 1e-6) {
                throw new InvalidArgumentException("probabilidade de {$rarity->id} tem mais de 4 casas decimais.");
            }
            $total += (int) round($scaled);
        }
        if ($total !== self::SCALE) {
            throw new InvalidArgumentException('as probabilidades das raridades devem somar 1.');
        }
        if ($chestsOnBossDefeat < 1) {
            throw new InvalidArgumentException('o boss deve deixar pelo menos um baú (RF26).');
        }
        CombatValidation::nonNegative($chancePerKill, 'chancePerKill');
        if ($chancePerKill > 1.0) {
            throw new InvalidArgumentException('chancePerKill deve ser no máximo 1.');
        }
        $this->rarities = $rarities;
    }

    public function drawRarity(RandomIndex $random): ChestRarity
    {
        $roll = $random->nextIndex(self::SCALE);
        $limit = 0;
        foreach ($this->rarities as $rarity) {
            $limit += (int) round($rarity->probability * self::SCALE);
            if ($roll < $limit) {
                return $rarity;
            }
        }

        // Inalcançável: as faixas somam SCALE, então o último já cobre o resto.
        return $this->rarities[count($this->rarities) - 1];
    }

    /** Sorteia se um inimigo comum deixa baú; o Tesouro (C9) entra com o multiplicador. */
    public function rollKillDrop(RandomIndex $random, float $chanceMultiplier = 1.0): bool
    {
        CombatValidation::nonNegative($chanceMultiplier, 'chanceMultiplier');
        $chance = min(1.0, $this->chancePerKill * $chanceMultiplier);

        return $random->nextIndex(self::SCALE) < (int) round($chance * self::SCALE);
    }
}
