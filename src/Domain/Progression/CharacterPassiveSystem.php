<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression;

use Jogo\Domain\Character\Player;
use Jogo\Domain\Event\EventBusInterface;
use Jogo\Domain\Event\LevelUp;

/**
 * Passiva única do personagem (RF39): a cada aCadaNiveis níveis concede o seu efeito.
 * Como LevelUp chega uma vez por nível, nenhum marco é pulado em ganhos grandes de XP.
 *
 * LevelUp pode ser publicado no meio de `WeaponAttackSystem::update()`, quando a Frente A
 * proíbe alterar armas. Por isso o marco só é enfileirado; `applyPending()` concede os
 * efeitos depois do combate, no ponto seguro chamado por Application.
 */
final class CharacterPassiveSystem
{
    /** @var list<int> */
    private array $grantedLevels = [];

    /** @var list<int> */
    private array $pendingLevels = [];

    public function __construct(private readonly Player $player, private readonly ProgressionEffects $effects) {}

    public function subscribe(EventBusInterface $events): void
    {
        $events->subscribe(LevelUp::class, function (LevelUp $event): void {
            if ($event->playerId === $this->player->id()) {
                $this->onLevel($event->level);
            }
        });
    }

    /** Enfileira o marco; devolve true quando o nível é um marco ainda não concedido nem pendente. */
    public function onLevel(int $level): bool
    {
        $passive = $this->player->character()->passive;
        if ($level % $passive->everyLevels !== 0
            || in_array($level, $this->grantedLevels, true)
            || in_array($level, $this->pendingLevels, true)) {
            return false;
        }
        $this->pendingLevels[] = $level;

        return true;
    }

    /**
     * Concede os marcos pendentes, em ordem. Deve ser chamado fora da atualização de combate.
     *
     * @return list<int> Níveis concedidos nesta chamada.
     */
    public function applyPending(): array
    {
        $passive = $this->player->character()->passive;
        $template = new ModifierTemplate($passive->attribute, $passive->type, $passive->value);
        $applied = [];
        while ($this->pendingLevels !== []) {
            $level = $this->pendingLevels[0];
            $this->effects->grant([$template->toModifier("personagem:{$passive->id}:{$level}")]);
            array_shift($this->pendingLevels);
            $this->grantedLevels[] = $level;
            $applied[] = $level;
        }

        return $applied;
    }

    /** @return list<int> */
    public function pendingLevels(): array
    {
        return $this->pendingLevels;
    }

    /** @return list<int> */
    public function grantedLevels(): array
    {
        return $this->grantedLevels;
    }
}
