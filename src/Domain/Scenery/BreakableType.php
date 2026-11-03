<?php

declare(strict_types=1);

namespace Jogo\Domain\Scenery;

use InvalidArgumentException;
use Jogo\Domain\Random\RandomIndex;
use Jogo\Domain\Value\CombatValidation;

/**
 * Tipo de elemento quebrável de scenery.json: Grass, Crate ou Ore (RF38).
 *
 * A tabela de drops precisa somar 1, com até 4 casas decimais, e o sorteio
 * é feito com inteiros de 0 a 9999, como nos baús.
 */
final readonly class BreakableType
{
    private const SCALE = 10_000;

    /** @param list<DropChance> $drops */
    public function __construct(
        public string $id,
        public string $name,
        public float $maxHealth,
        public float $collisionRadius,
        public bool $blocksMovement,
        public array $drops,
    ) {
        CombatValidation::requiredId($id, 'id');
        CombatValidation::requiredId($name, 'name');
        CombatValidation::positive($maxHealth, 'maxHealth');
        CombatValidation::positive($collisionRadius, 'collisionRadius');
        if ($drops === []) {
            throw new InvalidArgumentException('a tabela de drops não pode estar vazia.');
        }
        $total = 0;
        $items = [];
        foreach ($drops as $drop) {
            if (isset($items[$drop->itemId])) {
                throw new InvalidArgumentException("item repetido na tabela de drops: {$drop->itemId}.");
            }
            $items[$drop->itemId] = true;
            $scaled = $drop->probability * self::SCALE;
            if (abs($scaled - round($scaled)) > 1e-6) {
                throw new InvalidArgumentException("chance de {$drop->itemId} tem mais de 4 casas decimais.");
            }
            $total += (int) round($scaled);
        }
        if ($total !== self::SCALE) {
            throw new InvalidArgumentException('as chances da tabela de drops devem somar 1.');
        }
    }

    /** Sorteia o que cai; pode ser "nenhum". */
    public function drawDrop(RandomIndex $random): string
    {
        $roll = $random->nextIndex(self::SCALE);
        foreach ($this->drops as $drop) {
            $share = (int) round($drop->probability * self::SCALE);
            if ($roll < $share) {
                return $drop->itemId;
            }
            $roll -= $share;
        }

        // Inalcançável: as chances somam SCALE.
        return $this->drops[count($this->drops) - 1]->itemId;
    }
}
