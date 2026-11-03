<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Infrastructure\Play;

use InvalidArgumentException;
use Jogo\Domain\Ai\EntityView;
use Jogo\Domain\Ai\WorldView;
use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Play\BossHudSnapshot;
use Jogo\Infrastructure\Play\PlayEntityView;
use Jogo\Infrastructure\Play\PlayFrameFactory;
use Jogo\Infrastructure\Play\PlayFramePresenter;
use Jogo\Infrastructure\Play\PlayHudSnapshot;
use Jogo\Infrastructure\Play\PlaySnapshot;
use Jogo\Infrastructure\Play\PlaySnapshotProviderInterface;
use Jogo\Infrastructure\Play\PlayWorldSnapshot;
use Jogo\Infrastructure\Play\WeaponHudSnapshot;
use Jogo\Infrastructure\Play\WorldViewPlayAdapter;
use Jogo\Infrastructure\Rendering\FrameSnapshot;
use Jogo\Infrastructure\Rendering\RendererInterface;
use Jogo\Infrastructure\State\PlayState;
use Jogo\Infrastructure\State\StateStack;
use PHPUnit\Framework\TestCase;

final class PlayPresentationTest extends TestCase
{
    public function test_hud_contains_required_information(): void
    {
        $hud = $this->hud();

        self::assertEqualsWithDelta(
            0.75,
            $hud->healthRatio(),
            1.0E-9
        );

        self::assertEqualsWithDelta(
            0.5,
            $hud->xpRatio(),
            1.0E-9
        );

        self::assertSame('02:05', $hud->formattedTime());

        self::assertSame(
            ['Stone Sword Nv.2 CD 0.4s'],
            $hud->weaponLines()
        );

        self::assertSame(['Sharpness'], $hud->passives);
        self::assertSame(2, $hud->level);

        self::assertNotNull($hud->boss);

        self::assertEqualsWithDelta(
            0.5,
            $hud->boss->healthRatio(),
            1.0E-9
        );
    }

    public function test_frame_factory_maps_visual_types_and_layers(): void
    {
        $previous = new Vector2(100, 100);
        $current = new Vector2(120, 100);

        $world = new PlayWorldSnapshot(
            $previous,
            $current,
            [
                new PlayEntityView(
                    'enemy',
                    new Vector2(200, 100),
                    new Vector2(190, 100),
                    16
                ),
                new PlayEntityView(
                    'boss',
                    new Vector2(300, 200),
                    new Vector2(300, 200),
                    35
                ),
                new PlayEntityView(
                    'pickup',
                    new Vector2(140, 180),
                    new Vector2(140, 180),
                    8
                ),
                new PlayEntityView(
                    'projectile',
                    new Vector2(180, 140),
                    new Vector2(185, 140),
                    5
                ),
                new PlayEntityView(
                    'tnt',
                    new Vector2(250, 200),
                    new Vector2(250, 200),
                    20
                ),
                new PlayEntityView(
                    'sword',
                    new Vector2(160, 160),
                    new Vector2(160, 160),
                    15
                ),
                new PlayEntityView(
                    'pulse',
                    new Vector2(190, 190),
                    new Vector2(190, 190),
                    20
                ),
                new PlayEntityView(
                    'chest',
                    new Vector2(230, 230),
                    new Vector2(230, 230),
                    12
                ),
                new PlayEntityView(
                    'breakable',
                    new Vector2(130, 240),
                    new Vector2(130, 240),
                    12
                ),
            ],
        );

        $frame = (new PlayFrameFactory)->create(
            new PlaySnapshot($world, $this->hud())
        );

        self::assertCount(10, $frame->items);
        self::assertSame($previous, $frame->previousFocus);
        self::assertSame($current, $frame->currentFocus);
        self::assertSame('entities', $frame->items[0]->layer);
        self::assertSame('pickups', $frame->items[2]->layer);
        self::assertSame('attacks', $frame->items[3]->layer);
        self::assertSame('scenery', $frame->items[7]->layer);
        self::assertSame('entities', $frame->items[9]->layer);
        self::assertNotNull($frame->playHud);
    }

    public function test_snapshot_is_not_modified_by_factory(): void
    {
        $snapshot = $this->snapshot();
        $original = serialize($snapshot);

        (new PlayFrameFactory)->create($snapshot);

        self::assertSame($original, serialize($snapshot));
    }

    public function test_play_state_uses_presenter_without_updating_world(): void
    {
        $provider = new FakePlaySnapshotProvider($this->snapshot());
        $renderer = new FakePlayRenderer;

        $presenter = new PlayFramePresenter(
            $provider,
            new PlayFrameFactory,
            $renderer
        );

        $states = new StateStack;
        $states->push(
            new PlayState(presenter: $presenter)
        );

        $states->flush();
        $states->render(0.25);

        self::assertSame(1, $provider->reads);
        self::assertCount(1, $renderer->frames);
        self::assertEqualsWithDelta(
            0.25,
            $renderer->alphas[0],
            1.0E-9
        );
    }

    public function test_world_adapter_uses_read_only_entity_views(): void
    {
        $world = new class implements WorldView
        {
            public function playerPosition(): Vector2
            {
                return new Vector2(100, 100);
            }

            public function neighbors(Vector2 $center, float $radius): array
            {
                return [
                    new EntityView(
                        'enemy-1',
                        new Vector2(150, 100),
                        12.0,
                        'enemy',
                        true
                    ),
                    new EntityView(
                        'enemy-2',
                        new Vector2(170, 100),
                        12.0,
                        'enemy',
                        false
                    ),
                    new EntityView(
                        'chest-1',
                        new Vector2(190, 100),
                        10.0,
                        'chest',
                        null
                    ),
                ];
            }
        };

        $snapshot = (new WorldViewPlayAdapter(
            $world,
            500.0
        ))->snapshot();

        self::assertCount(2, $snapshot->entities);
        self::assertSame('enemy', $snapshot->entities[0]->kind);
        self::assertSame('chest', $snapshot->entities[1]->kind);
        self::assertTrue(
            $snapshot->currentPlayer->equals(new Vector2(100, 100))
        );
    }

    public function test_invalid_visual_kind_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PlayEntityView(
            'unknown',
            Vector2::zero(),
            Vector2::zero(),
            10.0
        );
    }

    public function test_invalid_health_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PlayHudSnapshot(
            health: 30.0,
            maxHealth: 20.0,
            xp: 0,
            xpToNextLevel: 10,
            level: 1,
            elapsedSeconds: 0.0
        );
    }

    public function test_invalid_boss_health_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BossHudSnapshot('Boss', -1.0, 100.0);
    }

    private function hud(): PlayHudSnapshot
    {
        return new PlayHudSnapshot(
            health: 15.0,
            maxHealth: 20.0,
            xp: 5,
            xpToNextLevel: 10,
            level: 2,
            elapsedSeconds: 125.0,
            weapons: [
                new WeaponHudSnapshot('Stone Sword', 2, 0.4),
            ],
            passives: ['Sharpness'],
            boss: new BossHudSnapshot('Boss', 50.0, 100.0),
            wave: 'Onda de inimigos'
        );
    }

    private function snapshot(): PlaySnapshot
    {
        $player = new Vector2(100, 100);

        return new PlaySnapshot(
            new PlayWorldSnapshot(
                $player,
                $player,
                []
            ),
            $this->hud()
        );
    }
}

final class FakePlaySnapshotProvider implements PlaySnapshotProviderInterface
{
    public int $reads = 0;

    public function __construct(
        private readonly PlaySnapshot $frame,
    ) {}

    public function snapshot(): PlaySnapshot
    {
        $this->reads++;

        return $this->frame;
    }
}

final class FakePlayRenderer implements RendererInterface
{
    /** @var list<FrameSnapshot> */
    public array $frames = [];

    /** @var list<float> */
    public array $alphas = [];

    public function render(FrameSnapshot $frame, float $alpha): void
    {
        $this->frames[] = $frame;
        $this->alphas[] = $alpha;
    }
}
