<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Feedback;

use InvalidArgumentException;

final readonly class FeedbackTypes
{
    public const ALL = [
        'player_damaged',
        'enemy_hit',
        'enemy_killed',
        'level_up',
        'chest_opened',
        'player_died',
        'item_picked',
    ];

    /** @return array{int, int, int} */
    public static function color(string $kind): array
    {
        return match ($kind) {
            'player_damaged' => [255, 75, 75],
            'enemy_hit' => [255, 180, 70],
            'enemy_killed' => [230, 80, 215],
            'level_up' => [255, 230, 65],
            'chest_opened' => [255, 180, 40],
            'player_died' => [200, 45, 55],
            'item_picked' => [75, 235, 145],
            default => throw new InvalidArgumentException(
                'Feedback desconhecido: '.$kind
            ),
        };
    }

    public static function label(string $kind): string
    {
        return match ($kind) {
            'player_damaged' => 'DANO!',
            'enemy_hit' => 'ACERTO!',
            'enemy_killed' => 'DERROTADO!',
            'level_up' => 'LEVEL UP!',
            'chest_opened' => 'BAU ABERTO!',
            'player_died' => 'GAME OVER!',
            'item_picked' => 'ITEM!',
            default => throw new InvalidArgumentException(
                'Feedback desconhecido: '.$kind
            ),
        };
    }
}
