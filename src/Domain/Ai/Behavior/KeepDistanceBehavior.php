<?php

declare(strict_types=1);

namespace Jogo\Domain\Ai\Behavior;

use Jogo\Domain\Ai\EntityView;
use Jogo\Domain\Ai\WorldView;
use Jogo\Domain\Enemy\RangedSettings;
use Jogo\Domain\Value\Vector2;

/**
 * Comportamento manter_distancia do Skeleton (RF36).
 *
 * Longe demais persegue, perto demais foge e, na faixa certa, anda de lado
 * em volta do jogador. A troca usa histerese: começa a orbitar ao chegar na
 * distância ideal, mas só volta a perseguir depois de passar de ideal +
 * margem, e só para de fugir ao recuar até a ideal de novo. Sem isso, um
 * Skeleton parado na fronteira trocaria de modo a cada passo e tremeria.
 *
 * Guarda o modo de cada inimigo pelo ID, porque a decisão depende de onde
 * ele estava no passo anterior.
 */
final class KeepDistanceBehavior implements BehaviorStrategy
{
    /** @var array<string, RangedMode> */
    private array $modes = [];

    public function __construct(private readonly RangedSettings $settings) {}

    public function direction(EntityView $enemy, WorldView $world, float $deltaSeconds): Vector2
    {
        $toPlayer = $world->playerPosition()->subtract($enemy->position);
        $distance = $toPlayer->length();
        $mode = $this->nextMode($this->modes['id:'.$enemy->id] ?? null, $distance);
        $this->modes['id:'.$enemy->id] = $mode;

        $toward = $toPlayer->normalized();

        return match ($mode) {
            RangedMode::CHASE => $toward,
            RangedMode::FLEE => $toward->scale(-1.0),
            RangedMode::ORBIT => self::sideways($toward, $enemy->id),
        };
    }

    /** Modo atual do inimigo; muda a cada direction(). */
    public function mode(string $enemyId): ?RangedMode
    {
        return $this->modes['id:'.$enemyId] ?? null;
    }

    /** Esquece um inimigo que saiu do mundo. */
    public function forget(string $enemyId): void
    {
        unset($this->modes['id:'.$enemyId]);
    }

    private function nextMode(?RangedMode $current, float $distance): RangedMode
    {
        $ideal = $this->settings->idealDistance;
        $min = $this->settings->minDistance();
        $max = $this->settings->maxDistance();

        return match ($current) {
            null => $distance > $ideal ? RangedMode::CHASE : ($distance < $min ? RangedMode::FLEE : RangedMode::ORBIT),
            RangedMode::CHASE => $distance <= $ideal ? RangedMode::ORBIT : RangedMode::CHASE,
            RangedMode::FLEE => $distance >= $ideal ? RangedMode::ORBIT : RangedMode::FLEE,
            RangedMode::ORBIT => $distance > $max ? RangedMode::CHASE : ($distance < $min ? RangedMode::FLEE : RangedMode::ORBIT),
        };
    }

    /** Perpendicular à direção do jogador; metade dos Skeletons gira para cada lado. */
    private static function sideways(Vector2 $toward, string $id): Vector2
    {
        $clockwise = crc32($id) % 2 === 0;

        return $clockwise ? new Vector2($toward->y(), -$toward->x()) : new Vector2(-$toward->y(), $toward->x());
    }
}
