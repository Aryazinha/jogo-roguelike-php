<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Infrastructure\Rendering;

use InvalidArgumentException;
use Jogo\Domain\Value\MapBounds;
use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Rendering\Camera;
use Jogo\Infrastructure\Rendering\FrameSnapshot;
use Jogo\Infrastructure\Rendering\NullRenderer;
use Jogo\Infrastructure\Rendering\RaylibResourceCache;
use Jogo\Infrastructure\Rendering\RendererInterface;
use Jogo\Infrastructure\Rendering\RenderItem;
use LogicException;
use PHPUnit\Framework\TestCase;

final class RendererTest extends TestCase
{
    public function test_camera_centers_player(): void
    {
        $camera = new Camera(800, 450, new MapBounds(0, 0, 1600, 1000));
        $screen = $camera->worldToScreen(
            new Vector2(600, 400),
            new Vector2(600, 400)
        );

        self::assertTrue($screen->equals(new Vector2(400, 225)));
    }

    public function test_camera_respects_map_limits(): void
    {
        $camera = new Camera(800, 450, new MapBounds(0, 0, 1600, 1000));

        self::assertTrue(
            $camera->topLeft(new Vector2(0, 0))->equals(new Vector2(0, 0))
        );

        self::assertTrue(
            $camera->topLeft(new Vector2(1600, 1000))
                ->equals(new Vector2(800, 550))
        );
    }

    public function test_camera_centers_small_maps(): void
    {
        $camera = new Camera(800, 450, new MapBounds(0, 0, 200, 100));

        self::assertTrue(
            $camera->topLeft(new Vector2(50, 50))
                ->equals(new Vector2(-300, -175))
        );
    }

    public function test_world_screen_conversion_is_reversible(): void
    {
        $camera = new Camera(800, 450, new MapBounds(0, 0, 1600, 1000));
        $focus = new Vector2(600, 400);
        $world = new Vector2(900, 500);

        self::assertTrue(
            $camera->screenToWorld(
                $camera->worldToScreen($world, $focus),
                $focus
            )->equals($world)
        );
    }

    public function test_interpolation_uses_alpha(): void
    {
        $result = Camera::interpolate(
            new Vector2(0, 20),
            new Vector2(100, 60),
            0.25
        );

        self::assertTrue($result->equals(new Vector2(25, 30)));
    }

    public function test_invalid_alpha_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Camera::interpolate(Vector2::zero(), Vector2::zero(), 1.5);
    }

    public function test_layers_have_a_fixed_order(): void
    {
        $zero = Vector2::zero();

        $effects = new RenderItem('effects', 'circle', $zero, $zero, 10);
        $map = new RenderItem('map', 'rectangle', $zero, $zero, 10);
        $player = new RenderItem('entities', 'circle', $zero, $zero, 10);

        $frame = new FrameSnapshot(
            $zero,
            $zero,
            [$effects, $player, $map]
        );

        self::assertSame(
            [$map, $player, $effects],
            $frame->orderedItems()
        );
    }

    public function test_null_renderer_does_not_mutate_snapshot(): void
    {
        $zero = Vector2::zero();

        $frame = new FrameSnapshot(
            $zero,
            new Vector2(10, 10),
            [new RenderItem('entities', 'circle', $zero, $zero, 20)],
            ['Vida: 100']
        );

        $original = serialize($frame);
        $renderer = new NullRenderer;

        self::assertInstanceOf(RendererInterface::class, $renderer);

        $renderer->render($frame, 0.5);

        self::assertSame($original, serialize($frame));
    }

    public function test_invalid_render_layer_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RenderItem(
            'unknown',
            'circle',
            Vector2::zero(),
            Vector2::zero(),
            10
        );
    }

    public function test_resource_cache_loads_once_and_unloads_once(): void
    {
        $loads = [];
        $unloads = [];

        $cache = new RaylibResourceCache(
            static function (string $path) use (&$loads): string {
                $loads[] = 'texture:'.$path;

                return 'texture-id';
            },
            static function (string $id) use (&$unloads): void {
                $unloads[] = 'texture:'.$id;
            },
            static function (string $path) use (&$loads): string {
                $loads[] = 'font:'.$path;

                return 'font-id';
            },
            static function (string $id) use (&$unloads): void {
                $unloads[] = 'font:'.$id;
            },
        );

        self::assertSame('texture-id', $cache->texture('a.png'));
        self::assertSame('texture-id', $cache->texture('a.png'));
        self::assertSame('font-id', $cache->font('a.ttf'));
        self::assertSame('font-id', $cache->font('a.ttf'));

        $cache->release();
        $cache->release();

        self::assertSame(
            ['texture:a.png', 'font:a.ttf'],
            $loads
        );

        self::assertSame(
            ['font:font-id', 'texture:texture-id'],
            $unloads
        );
    }

    public function test_closed_cache_cannot_load_more_assets(): void
    {
        $cache = new RaylibResourceCache(
            static fn (string $path): string => $path,
            static function (string $id): void {},
            static fn (string $path): string => $path,
            static function (string $id): void {},
        );

        $cache->release();

        $this->expectException(LogicException::class);

        $cache->texture('a.png');
    }
}
