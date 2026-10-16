<?php

declare(strict_types=1);

namespace Jogo\Domain\Progression;

use Jogo\Domain\Character\Player;
use Jogo\Domain\Event\EventBusInterface;
use Jogo\Domain\Event\LevelUp;

/**
 * Passiva única do personagem (RF39): a cada aCadaNiveis níveis concede o seu efeito.
 * Como LevelUp chega uma vez por nível, nenhum marco é pulado em ganhos grandes de XP.
 */
final class CharacterPassiveSystem
{
    /** @var list<int> */
    private array $grantedLevels = [];

    public function __construct(private readonly Player $player, private readonly ProgressionEffects $effects) {}

    public function subscribe(EventBusInterface $events): void
    {
        $events->subscribe(LevelUp::class, function (LevelUp $event): void {
            if ($event->playerId === $this->player->id()) {
                $this->onLevel($event->level);
            }
        });
    }

    /** Devolve true quando o nível é um marco ainda não concedido. */
    public function onLevel(int $level): bool
    {
        $passive = $this->player->character()->passive;
        if ($level % $passive->everyLevels !== 0 || in_array($level, $this->grantedLevels, true)) {
            return false;
        }
        $template = new ModifierTemplate($passive->attribute, $passive->type, $passive->value);
        $this->effects->grant([$template->toModifier("personagem:{$passive->id}:{$level}")]);
        $this->grantedLevels[] = $level;

        return true;
    }

    /** @return list<int> */
    public function grantedLevels(): array
    {
        return $this->grantedLevels;
    }
}
