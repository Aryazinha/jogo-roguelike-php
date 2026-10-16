<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Core\Config;

use Jogo\Core\Config\ConfigJsonReader;
use Jogo\Core\Config\ConfigSchema;
use Jogo\Core\Config\MapConfigLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class MapConfigLoaderTest extends TestCase
{
    public function test_real_file_is_loaded(): void
    {
        $layout = (new MapConfigLoader)->load(self::file());

        self::assertSame([4000.0, 4000.0, 32.0, 760.0, 960.0], [$layout->width, $layout->height,
            $layout->borderMargin, $layout->spawnMinDistance, $layout->spawnMaxDistance]);
    }

    /** @return iterable<string, array{string, mixed, string}> */
    public static function invalidFields(): iterable
    {
        yield 'missing width' => ['larguraPixels', null, 'mapa.larguraPixels deve ser um número'];
        yield 'text height' => ['alturaPixels', '4000', 'mapa.alturaPixels deve ser um número'];
        yield 'unknown field' => ['profundidade', 3, 'mapa.profundidade é um campo de gameplay desconhecido'];
        yield 'missing source' => ['_fonte', null, 'mapa._fonte deve ser uma string'];
        yield 'domain rule' => ['distanciaMaximaSpawnPixels', 100, 'mapa: distanciaMaximaSpawnPixels deve ser maior ou igual'];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_field_is_reported(string $field, mixed $value, string $message): void
    {
        $root = ConfigSchema::object(ConfigJsonReader::load(self::file()), 'test');
        $map = ConfigSchema::object($root->mapa, 'test');
        if ($value === null) {
            unset($map->{$field});
        } else {
            $map->{$field} = $value;
        }

        $this->expectExceptionMessage($message);

        (new MapConfigLoader)->decode(json_encode($root, JSON_THROW_ON_ERROR));
    }

    public function test_extra_root_field_is_rejected(): void
    {
        $root = ConfigSchema::object(ConfigJsonReader::load(self::file()), 'test');
        $root->bioma = new stdClass;

        $this->expectExceptionMessage('bioma é um campo de gameplay desconhecido');

        (new MapConfigLoader)->decode(json_encode($root, JSON_THROW_ON_ERROR));
    }

    private static function file(): string
    {
        return dirname(__DIR__, 4).'/config/map.json';
    }
}
