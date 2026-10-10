<?php

declare(strict_types=1);

namespace Jogo\Domain\Weapon;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;

/** Dados compartilháveis da arma; identificadores não executam mira ou comportamento. */
final readonly class WeaponDefinition
{
    public const TARGETING_BY_SHAPE = [
        'meia_lua' => 'direcao_do_olhar',
        'projetil' => 'inimigo_mais_proximo',
        'area' => 'inimigo_aleatorio',
        'pulso' => 'area_ao_redor',
    ];

    public const SPECIFIC_FIELDS_BY_SHAPE = [
        'meia_lua' => ['angleDegrees'],
        'projetil' => ['projectileCount', 'projectileSpeed'],
        'area' => ['explosionRadius', 'detonationDelaySeconds'],
        'pulso' => [],
    ];

    public function __construct(
        public string $id,
        public string $name,
        public float $damage,
        public float $cooldownSeconds,
        public string $targetingId,
        public string $shapeId,
        public float $range,
        public ?float $angleDegrees = null,
        public ?int $projectileCount = null,
        public ?float $projectileSpeed = null,
        public ?float $explosionRadius = null,
        public ?float $detonationDelaySeconds = null,
    ) {
        foreach (['id' => $id, 'name' => $name, 'targetingId' => $targetingId, 'shapeId' => $shapeId] as $field => $value) {
            CombatValidation::requiredId($value, $field);
        }
        CombatValidation::nonNegative($damage, 'damage');
        CombatValidation::positive($cooldownSeconds, 'cooldownSeconds');
        CombatValidation::positive($range, 'range');

        if (! isset(self::TARGETING_BY_SHAPE[$shapeId])) {
            throw new InvalidArgumentException('shapeId desconhecido.');
        }
        if (self::TARGETING_BY_SHAPE[$shapeId] !== $targetingId) {
            throw new InvalidArgumentException('targetingId incompatível com shapeId.');
        }

        $required = self::SPECIFIC_FIELDS_BY_SHAPE[$shapeId];
        $specific = ['angleDegrees' => $angleDegrees, 'projectileCount' => $projectileCount,
            'projectileSpeed' => $projectileSpeed, 'explosionRadius' => $explosionRadius,
            'detonationDelaySeconds' => $detonationDelaySeconds];
        foreach ($specific as $field => $value) {
            if (in_array($field, $required, true) && $value === null) {
                throw new InvalidArgumentException("{$field} é obrigatório para shapeId {$shapeId}.");
            }
            if (! in_array($field, $required, true) && $value !== null) {
                throw new InvalidArgumentException("{$field} não se aplica a shapeId {$shapeId}.");
            }
        }

        if ($angleDegrees !== null) {
            CombatValidation::positive($angleDegrees, 'angleDegrees');
            if ($angleDegrees > 360.0) {
                throw new InvalidArgumentException('angleDegrees deve ser menor ou igual a 360.');
            }
        }
        if ($projectileCount !== null && $projectileCount < 1) {
            throw new InvalidArgumentException('projectileCount deve ser inteiro e maior ou igual a um.');
        }
        if ($projectileSpeed !== null) {
            CombatValidation::positive($projectileSpeed, 'projectileSpeed');
        }
        if ($explosionRadius !== null) {
            CombatValidation::positive($explosionRadius, 'explosionRadius');
        }
        if ($detonationDelaySeconds !== null) {
            CombatValidation::nonNegative($detonationDelaySeconds, 'detonationDelaySeconds');
        }
    }
}
