<?php

declare(strict_types=1);

namespace Jogo\Domain\Enemy;

use InvalidArgumentException;
use Jogo\Domain\Value\CombatValidation;

/**
 * Dados fixos de um tipo de inimigo, compartilhados por todas as instâncias (Flyweight).
 *
 * Não guarda vida atual nem posição: isso fica em Enemy. O comportamento é
 * apenas um identificador; quem executa é a estratégia da C3/C5/C6.
 */
final readonly class EnemyArchetype
{
    public const BEHAVIOR_CHASE = 'perseguir';

    public const BEHAVIOR_KEEP_DISTANCE = 'manter_distancia';

    public const BEHAVIOR_BOSS = 'boss_em_fases';

    public const BEHAVIORS = [self::BEHAVIOR_CHASE, self::BEHAVIOR_KEEP_DISTANCE, self::BEHAVIOR_BOSS];

    public function __construct(
        public string $id,
        public string $name,
        public EnemyCategory $category,
        public float $maxHealth,
        public float $damage,
        public int $xpMin,
        public int $xpMax,
        public float $speed,
        public float $collisionRadius,
        public string $behaviorId,
        public ?int $minLevel = null,
        public ?float $spawnAtSeconds = null,
        public ?SplitRule $split = null,
        public bool $onlyFromSplit = false,
        public ?RangedSettings $ranged = null,
    ) {
        CombatValidation::requiredId($id, 'id');
        CombatValidation::requiredId($name, 'name');
        CombatValidation::positive($maxHealth, 'maxHealth');
        CombatValidation::nonNegative($damage, 'damage');
        CombatValidation::nonNegative($speed, 'speed');
        CombatValidation::positive($collisionRadius, 'collisionRadius');

        if ($xpMin < 0 || $xpMax < $xpMin) {
            throw new InvalidArgumentException('xp deve ter min >= 0 e max >= min.');
        }
        if (! in_array($behaviorId, self::BEHAVIORS, true)) {
            throw new InvalidArgumentException("behaviorId desconhecido: {$behaviorId}.");
        }

        $this->assertCategoryRules();

        if (($behaviorId === self::BEHAVIOR_KEEP_DISTANCE) !== ($ranged !== null)) {
            throw new InvalidArgumentException('ranged é obrigatório só para o comportamento manter_distancia.');
        }
    }

    public function isBoss(): bool
    {
        return $this->category === EnemyCategory::BOSS;
    }

    /** Se o tipo pode entrar no sorteio de spawn para o nível do jogador (RF05). */
    public function canSpawnAtLevel(int $level): bool
    {
        if ($this->isBoss() || $this->onlyFromSplit || $this->minLevel === null) {
            return false;
        }

        return $level >= $this->minLevel;
    }

    private function assertCategoryRules(): void
    {
        if ($this->isBoss()) {
            // O boss tem hora marcada para surgir, não nível mínimo.
            if ($this->minLevel !== null || $this->spawnAtSeconds === null) {
                throw new InvalidArgumentException('boss usa spawnAtSeconds em vez de minLevel.');
            }
            CombatValidation::nonNegative($this->spawnAtSeconds, 'spawnAtSeconds');
            if ($this->behaviorId !== self::BEHAVIOR_BOSS) {
                throw new InvalidArgumentException('boss deve usar o comportamento boss_em_fases.');
            }
        } else {
            if ($this->minLevel === null || $this->minLevel < 1) {
                throw new InvalidArgumentException('minLevel deve ser inteiro maior ou igual a um.');
            }
            if ($this->spawnAtSeconds !== null) {
                throw new InvalidArgumentException('spawnAtSeconds só se aplica ao boss.');
            }
            if ($this->behaviorId === self::BEHAVIOR_BOSS) {
                throw new InvalidArgumentException('boss_em_fases só se aplica ao boss.');
            }
        }

        if ($this->category !== EnemyCategory::SPECIAL && ($this->split !== null || $this->onlyFromSplit)) {
            throw new InvalidArgumentException('divisão ao morrer só se aplica a inimigos especiais.');
        }
        if ($this->split !== null && $this->split->childTypeId === $this->id) {
            throw new InvalidArgumentException('um inimigo não pode se dividir em si mesmo.');
        }
    }
}
