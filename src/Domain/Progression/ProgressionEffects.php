<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression;

use InvalidArgumentException;
use Jogo\Domain\Character\Player;
use Jogo\Domain\Value\StatModifier;
use Jogo\Domain\Weapon\ResolvedWeaponStats;
use Jogo\Domain\Weapon\Upgrade\WeaponProgressionService;
use Jogo\Domain\Weapon\WeaponStatsMapper;

/**
 * Encaminha efeitos de passivos e passivas: atributos do jogador vão para o Player e os de
 * arma vão, pela Frente A, para toda arma equipada que possua o atributo.
 *
 * Efeitos de arma ficam registrados para alcançar armas adquiridas ou evoluídas depois.
 * Se um efeito deixaria uma arma inválida (por exemplo, cooldown <= 0), ele é ignorado só
 * para aquela arma, sem limite mínimo inventado. A tolerância cobre apenas o arredondamento:
 * 1,8 - 9 x 0,2 resulta em 2e-16 em ponto flutuante, o que faria a arma atacar em todo passo.
 */
final class ProgressionEffects
{
    public const ROUNDING_TOLERANCE = 1.0E-9;

    /** @var list<StatModifier> */
    private array $weaponEffects = [];

    public function __construct(private readonly Player $player, private readonly WeaponProgressionService $weapons) {}

    /** @param list<StatModifier> $modifiers */
    public function grant(array $modifiers): void
    {
        $playerModifiers = [];
        $weaponModifiers = [];
        foreach ($modifiers as $modifier) {
            if ($this->isRegistered($modifier->source)) {
                throw new InvalidArgumentException("source duplicada: {$modifier->source}.");
            }
            if (in_array($modifier->attribute, Player::ATTRIBUTES, true)) {
                $playerModifiers[] = $modifier;
            } else {
                $weaponModifiers[] = $modifier;
            }
        }
        if ($playerModifiers !== []) {
            $this->player->applyModifiers($playerModifiers);
        }
        if ($weaponModifiers === []) {
            return;
        }
        $this->weaponEffects = array_merge($this->weaponEffects, $weaponModifiers);
        foreach (array_keys($this->player->inventory()->weapons()) as $slot) {
            $this->syncWeapon($slot);
        }
    }

    /**
     * Aplica à arma do slot os efeitos registrados que ela ainda não possui.
     *
     * @return list<string> Origens aplicadas agora.
     */
    public function syncWeapon(int $slot): array
    {
        $weapon = $this->player->inventory()->at($slot)
            ?? throw new InvalidArgumentException("O slot {$slot} não contém uma arma equipada.");
        $attributes = WeaponStatsMapper::fromDefinition($weapon->baseDefinition());
        $applied = [];
        foreach ($this->weaponEffects as $modifier) {
            if (! $attributes->has($modifier->attribute) || $weapon->modifier($modifier->source) !== null) {
                continue;
            }
            try {
                $next = new ResolvedWeaponStats($weapon->baseDefinition(), array_merge($weapon->modifiers(), [$modifier]));
                if ($next->definition->cooldownSeconds <= self::ROUNDING_TOLERANCE) {
                    continue;
                }
                $this->weapons->apply($slot, [$modifier]);
                $applied[] = $modifier->source;
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        return $applied;
    }

    /** @return list<StatModifier> */
    public function weaponEffects(): array
    {
        return $this->weaponEffects;
    }

    private function isRegistered(string $source): bool
    {
        if ($this->player->modifier($source) !== null) {
            return true;
        }
        foreach ($this->weaponEffects as $modifier) {
            if ($modifier->source === $source) {
                return true;
            }
        }

        return false;
    }
}
