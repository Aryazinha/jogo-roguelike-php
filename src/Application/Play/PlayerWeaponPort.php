<?php

declare(strict_types=1);

namespace Jogo\Application\Play;

use Jogo\Domain\System\WeaponAttackSystem;
use Jogo\Domain\Weapon\WeaponDefinition;
use Jogo\Domain\Weapon\WeaponMutationGuard;
use LogicException;

/**
 * Porta entre as armas do jogador (B cria, A executa).
 *
 * O jogador precisa do executor de ataques ao nascer, mas o executor da A
 * precisa do mundo, que por sua vez precisa do jogador. A porta quebra esse
 * ciclo: nasce vazia, entra no jogador e é ligada ao WeaponAttackSystem depois.
 *
 * Também anota cada golpe disparado, para o cenário (C) receber o mesmo dano
 * depois do combate: as armas da A só miram em inimigos (CONTRATOS.md §7.1).
 */
final class PlayerWeaponPort implements WeaponMutationGuard
{
    private ?WeaponAttackSystem $combat = null;

    /** @var list<array{float, float}> Alcance e dano de cada golpe disparado. */
    private array $swings = [];

    public function connect(WeaponAttackSystem $combat): void
    {
        if ($this->combat !== null) {
            throw new LogicException('A porta de armas já está ligada.');
        }
        $this->combat = $combat;
    }

    public function __invoke(WeaponDefinition $definition): bool
    {
        $combat = $this->combat ?? throw new LogicException('A porta de armas ainda não foi ligada ao combate.');
        $fired = $combat($definition);
        if ($fired) {
            $this->swings[] = [$definition->range, $definition->damage];
        }

        return $fired;
    }

    public function assertSafe(): void
    {
        $this->combat?->assertSafe();
    }

    /**
     * Golpes disparados desde a última chamada; cada um sai uma vez só.
     *
     * @return list<array{float, float}>
     */
    public function takeSwings(): array
    {
        $swings = $this->swings;
        $this->swings = [];

        return $swings;
    }
}
