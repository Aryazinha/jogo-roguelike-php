<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Infrastructure\Assets;

use Jogo\Domain\Value\Vector2;
use Jogo\Infrastructure\Assets\AssetCatalog;
use Jogo\Infrastructure\Play\PlayEntityView;
use Jogo\Infrastructure\Play\PlayFrameFactory;
use Jogo\Infrastructure\Play\PlayHudSnapshot;
use Jogo\Infrastructure\Play\PlaySnapshot;
use Jogo\Infrastructure\Play\PlayWorldSnapshot;
use PHPUnit\Framework\TestCase;

final class AssetCatalogTest extends TestCase
{
    private function catalog(): AssetCatalog
    {
        return AssetCatalog::fromProject(dirname(__DIR__, 4));
    }

    public function test_all_required_assets_exist_and_have_credits(): void
    {
        $catalog = $this->catalog();

        $catalog->assertReady();

        self::assertCount(20, $catalog->sprites());
        self::assertCount(7, $catalog->effects());
        self::assertCount(2, $catalog->tracks());
    }

    public function test_sprites_are_valid_png_files(): void
    {
        foreach ($this->catalog()->sprites() as $path) {
            $image = getimagesize($path);

            self::assertIsArray($image);
            self::assertSame(32, $image[0]);
            self::assertSame(32, $image[1]);
            self::assertSame(IMAGETYPE_PNG, $image[2]);
        }
    }

    public function test_audio_files_are_wav(): void
    {
        $catalog = $this->catalog();

        $paths = array_merge(
            $catalog->effects(),
            $catalog->tracks()
        );

        foreach ($paths as $path) {
            $header = file_get_contents(
                $path,
                false,
                null,
                0,
                12
            );

            self::assertIsString($header);
            self::assertSame('RIFF', substr($header, 0, 4));
            self::assertSame('WAVE', substr($header, 8, 4));
        }
    }

    public function test_player_and_enemies_use_textures_when_enabled(): void
    {
        $position = new Vector2(150.0, 150.0);

        $snapshot = new PlaySnapshot(
            new PlayWorldSnapshot(
                $position,
                $position,
                [
                    new PlayEntityView(
                        'enemy',
                        new Vector2(200.0, 150.0),
                        new Vector2(200.0, 150.0),
                        18.0
                    ),
                ],
            ),
            new PlayHudSnapshot(
                20.0,
                20.0,
                0,
                10,
                1,
                0.0
            )
        );

        $frame = (new PlayFrameFactory(
            null,
            $this->catalog()
        ))->create($snapshot);

        self::assertCount(2, $frame->items);
        self::assertSame('texture', $frame->items[0]->kind);
        self::assertSame('texture', $frame->items[1]->kind);
        self::assertNotNull($frame->items[0]->assetPath);
        self::assertNotNull($frame->items[1]->assetPath);
    }

    public function test_original_shape_renderer_still_works(): void
    {
        $position = new Vector2(150.0, 150.0);

        $snapshot = new PlaySnapshot(
            new PlayWorldSnapshot($position, $position),
            new PlayHudSnapshot(20.0, 20.0, 0, 10, 1, 0.0)
        );

        $frame = (new PlayFrameFactory)->create($snapshot);

        self::assertSame('circle', $frame->items[0]->kind);
    }
}
