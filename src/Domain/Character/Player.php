<?php

declare(strict_types=1);

namespace Jogo\Domain\Character;

use InvalidArgumentException;
use Jogo\Domain\Entity\Damageable;
use Jogo\Domain\Entity\Entity;
use Jogo\Domain\Value\Health;
use Jogo\Domain\Value\StatModifier;
use Jogo\Domain\Value\StatResolver;
use Jogo\Domain\Value\Stats;
use Jogo\Domain\Value\Vector2;
use Jogo\Domain\Weapon\CombatInventory;

/**
 * Jogador real: compõe vida, inventário e atributos base da Frente A com o personagem escolhido.
 *
 * Política de vida máxima (decisão técnica B2): ao aumentar, a vida atual ganha a mesma
 * diferença; ao diminuir, fica limitada ao novo máximo; jogador morto continua morto.
 * Health é substituída nessas mudanças, então consumidores devem sempre chamar health().
 */
final class Player extends Entity implements Damageable
{
    public const ATTRIBUTES = ['vidaMaxima', 'velocidadeJogador', 'raioColeta'];

    public const OPTIONAL_BASE_ATTRIBUTES = ['velocidadeJogador', 'raioColeta'];

    private Health $health;

    private readonly Stats $baseStats;

    private Stats $stats;

    private readonly HealthRegeneration $regeneration;

    /** @var list<StatModifier> */
    private array $modifiers = [];

    /** @param array<string, float> $optionalBaseStats Bases ainda sem valor no enunciado/JSON. */
    public function __construct(
        string $id,
        Vector2 $position,
        float $collisionRadius,
        private readonly CharacterDefinition $character,
        private readonly CombatInventory $inventory,
        array $optionalBaseStats = [],
    ) {
        parent::__construct($id, $position, $collisionRadius);
        if ($inventory->find($character->startingWeaponId) === null) {
            throw new InvalidArgumentException("O inventário precisa conter a arma inicial {$character->startingWeaponId}.");
        }
        foreach (array_keys($optionalBaseStats) as $attribute) {
            if (! in_array($attribute, self::OPTIONAL_BASE_ATTRIBUTES, true)) {
                throw new InvalidArgumentException("{$attribute} não é uma base opcional do jogador.");
            }
        }
        $this->baseStats = new Stats(['vidaMaxima' => $character->maxHealth] + $optionalBaseStats);
        $this->stats = $this->baseStats;
        $this->health = new Health($character->maxHealth);
        $this->regeneration = new HealthRegeneration($character->regenerationHp, $character->regenerationIntervalSeconds);
    }

    public function character(): CharacterDefinition
    {
        return $this->character;
    }

    public function health(): Health
    {
        return $this->health;
    }

    public function inventory(): CombatInventory
    {
        return $this->inventory;
    }

    public function regeneration(): HealthRegeneration
    {
        return $this->regeneration;
    }

    public function baseStats(): Stats
    {
        return $this->baseStats;
    }

    public function stats(): Stats
    {
        return $this->stats;
    }

    /** @return list<StatModifier> */
    public function modifiers(): array
    {
        return $this->modifiers;
    }

    public function modifier(string $source): ?StatModifier
    {
        foreach ($this->modifiers as $modifier) {
            if ($modifier->source === $source) {
                return $modifier;
            }
        }

        return null;
    }

    /** Chamado a cada passo fixo; com o jogo pausado não há passo, então nada avança. */
    public function regenerate(float $deltaSeconds): float
    {
        return $this->regeneration->advance($this->health, $deltaSeconds);
    }

    /**
     * Aplica todos os efeitos ou nenhum. Efeitos sobre bases ausentes não alteram nada.
     *
     * @param  list<StatModifier>  $modifiers
     */
    public function applyModifiers(array $modifiers): void
    {
        foreach ($modifiers as $modifier) {
            if (! in_array($modifier->attribute, self::ATTRIBUTES, true)) {
                throw new InvalidArgumentException("{$modifier->source}: {$modifier->attribute} não é um atributo do jogador.");
            }
        }
        $this->commit(array_merge($this->modifiers, $modifiers));
    }

    public function removeModifier(string $source): bool
    {
        if ($this->modifier($source) === null) {
            return false;
        }
        $this->commit(array_values(array_filter(
            $this->modifiers,
            static fn (StatModifier $modifier): bool => $modifier->source !== $source,
        )));

        return true;
    }

    /** @param list<StatModifier> $next */
    private function commit(array $next): void
    {
        $resolved = (new StatResolver)->resolve($this->baseStats, $next, ignoreMissing: true);
        usort($next, static fn (StatModifier $a, StatModifier $b): int => strcmp($a->source, $b->source));
        $this->health = $this->resizedHealth($resolved->value('vidaMaxima'));
        $this->stats = $resolved;
        $this->modifiers = $next;
    }

    private function resizedHealth(float $maximum): Health
    {
        $previous = $this->health;
        if ($maximum === $previous->maximum()) {
            return $previous;
        }
        if ($previous->isDead()) {
            return new Health($maximum, 0.0);
        }
        $gain = max(0.0, $maximum - $previous->maximum());

        return new Health($maximum, min($maximum, $previous->current() + $gain));
    }
}
