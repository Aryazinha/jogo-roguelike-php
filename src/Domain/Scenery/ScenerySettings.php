<?php

declare(strict_types=1);

namespace Jogo\Domain\Scenery;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;

/**
 * Regras de scenery.json: densidade, tipos de quebrável e bônus temporários.
 *
 * A densidade é lida como elementos por bloco de 1000 × 1000 px. "Por mil
 * unidades quadradas", ao pé da letra, daria 8 por 1000 px² e 128 mil
 * elementos num mapa de 4000 × 4000, o que não cabe na tela nem no RNF06.
 */
final readonly class ScenerySettings
{
    /** Área de referência da densidade: 1000 × 1000 px. */
    public const DENSITY_AREA = 1_000_000.0;

    /** @var array<string, BreakableType> */
    private array $types;

    /** @var array<string, TemporaryBonus> */
    private array $bonuses;

    /**
     * @param  list<BreakableType>  $types
     * @param  list<TemporaryBonus>  $bonuses
     */
    public function __construct(public float $density, array $types, array $bonuses)
    {
        CombatValidation::nonNegative($density, 'density');
        if ($types === [] || $bonuses === []) {
            throw new InvalidArgumentException('é preciso pelo menos um tipo de quebrável e um bônus temporário.');
        }
        $indexed = [];
        foreach ($types as $type) {
            $indexed[$type->id] = $type;
        }
        $bonusIndex = [];
        foreach ($bonuses as $bonus) {
            $bonusIndex[$bonus->id] = $bonus;
        }
        if (count($indexed) !== count($types) || count($bonusIndex) !== count($bonuses)) {
            throw new InvalidArgumentException('ID repetido em scenery.json.');
        }
        $this->types = $indexed;
        $this->bonuses = $bonusIndex;
    }

    /** @return list<BreakableType> */
    public function types(): array
    {
        return array_values($this->types);
    }

    public function type(string $id): BreakableType
    {
        return $this->types[$id] ?? throw new InvalidArgumentException("quebrável desconhecido: {$id}.");
    }

    /** @return list<TemporaryBonus> */
    public function bonuses(): array
    {
        return array_values($this->bonuses);
    }

    /** Quantos elementos cabem num mapa com essa área útil. */
    public function countFor(float $area): int
    {
        return (int) round($this->density * $area / self::DENSITY_AREA);
    }
}
