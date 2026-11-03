<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Play;

use Jogo\Infrastructure\Assets\AssetCatalog;
use Jogo\Infrastructure\Feedback\VisualFeedbackPool;
use Jogo\Infrastructure\Rendering\FrameSnapshot;
use Jogo\Infrastructure\Rendering\RenderItem;

final readonly class PlayFrameFactory
{
    public function __construct(
        private ?VisualFeedbackPool $feedback = null,
        private ?AssetCatalog $catalog = null,
    ) {}

    public function create(PlaySnapshot $snapshot): FrameSnapshot
    {
        $world = $snapshot->world;
        $items = [];

        foreach ($world->entities as $entity) {
            [$layer, $shape, $red, $green, $blue] = match ($entity->kind) {
                'map' => ['map', 'rectangle', 46, 77, 56],
                'scenery' => ['scenery', 'rectangle', 116, 86, 55],
                'enemy' => ['entities', 'circle', 205, 76, 70],
                'special_enemy' => ['entities', 'circle', 170, 75, 190],
                'boss' => ['entities', 'circle', 125, 30, 36],
                'projectile' => ['attacks', 'circle', 245, 245, 235],
                'tnt' => ['attacks', 'rectangle', 205, 58, 48],
                'sword' => ['attacks', 'rectangle', 195, 205, 220],
                'pulse' => ['attacks', 'circle', 60, 195, 240],
                'chest' => ['scenery', 'rectangle', 195, 137, 53],
                'pickup' => ['pickups', 'circle', 250, 210, 55],
                'breakable' => ['scenery', 'rectangle', 145, 105, 70],
                'effect' => ['effects', 'circle', 70, 190, 250],
                default => throw new \LogicException(
                    'Tipo visual nao suportado.'
                ),
            };

            $sprite = $this->catalog?->sprite($entity->kind);

            $items[] = new RenderItem(
                $layer,
                $sprite !== null ? 'texture' : $shape,
                $entity->previous,
                $entity->current,
                $entity->radius * 2.0,
                $red,
                $green,
                $blue,
                $sprite,
            );
        }

        $playerSprite = $this->catalog?->sprite('player');

        $items[] = new RenderItem(
            'entities',
            $playerSprite !== null ? 'texture' : 'circle',
            $world->previousPlayer,
            $world->currentPlayer,
            $world->playerRadius * 2.0,
            250,
            210,
            35,
            $playerSprite,
        );

        $feedbackFrame = $this->feedback?->frame();

        $feedbackLabels = [];

        if ($feedbackFrame !== null) {
            $items = array_merge($items, $feedbackFrame->items);
            $feedbackLabels = $feedbackFrame->labels;
        }

        return new FrameSnapshot(
            $world->previousPlayer,
            $world->currentPlayer,
            $items,
            [],
            $snapshot->hud,
            null,
            $feedbackLabels,
        );
    }
}
