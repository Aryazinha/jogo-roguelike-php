<?php

declare(strict_types=1);

namespace Jogo\Application\Graphical;

use Jogo\Application\Progression\ProgressionSession;
use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Play\PlayEntityView;
use Jogo\Infrastructure\Play\PlayHudSnapshot;
use Jogo\Infrastructure\Play\PlaySnapshot;
use Jogo\Infrastructure\Play\PlaySnapshotProviderInterface;
use Jogo\Infrastructure\Play\PlayWorldSnapshot;
use Jogo\Infrastructure\Play\WeaponHudSnapshot;

/** Le a progressao real B, sem alterar nenhuma regra do dominio. */
final class ProgressionSnapshotProvider implements PlaySnapshotProviderInterface
{
    private Vector2 $previousPosition;

    /** @var list<PlayEntityView> */
    private array $decorativeTiles = [];

    public function __construct(private readonly ProgressionSession $session)
    {
        $this->previousPosition = $session->player->position();

        // Piso visual temporario: a geometria definitiva pertence a Frente C.
        for ($x = 32; $x < 1600; $x += 64) {
            for ($y = 32; $y < 1024; $y += 64) {
                $position = new Vector2((float) $x, (float) $y);
                $this->decorativeTiles[] = new PlayEntityView(
                    'map',
                    $position,
                    $position,
                    32.0
                );
            }
        }
    }

    public function capturePrevious(): void
    {
        $this->previousPosition = $this->session->player->position();
    }

    /** @phpstan-impure */
    public function snapshot(): PlaySnapshot
    {
        $player = $this->session->player;
        $xp = $this->session->experience->experience();
        $weapons = [];

        foreach ($player->inventory()->weapons() as $weapon) {
            $baseId = $this->session->weaponLevels->baseIdOf(
                $weapon->definition()->id
            );
            $level = $baseId === null
                ? 1
                : $this->session->weaponLevels->level($baseId);

            $weapons[] = new WeaponHudSnapshot(
                $weapon->definition()->id,
                $level,
                $weapon->remainingSeconds(),
            );
        }

        $passives = [];
        foreach ($this->session->passives->owned() as $id => $level) {
            $passives[] = $id.' Nv.'.$level;
        }

        return new PlaySnapshot(
            new PlayWorldSnapshot(
                $this->previousPosition,
                $player->position(),
                $this->decorativeTiles,
                $player->collisionRadius(),
            ),
            new PlayHudSnapshot(
                $player->health()->current(),
                $player->health()->maximum(),
                $xp->currentXp(),
                $xp->requiredForNextLevel(),
                $xp->level(),
                $this->session->run->survivalSeconds(),
                $weapons,
                $passives,
                wave: 'INTEGRACAO C: PENDENTE',
            ),
        );
    }
}
