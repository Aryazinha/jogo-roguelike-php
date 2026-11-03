<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Core\Config;

use Jogo\Core\Config\ConfigJsonReader;
use Jogo\Core\Config\ConfigSchema;
use Jogo\Core\Config\ContactConfigLoader;
use Jogo\Core\Config\EnemyConfigLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContactConfigLoaderTest extends TestCase
{
    public function test_real_file_is_loaded_and_still_accepted_by_the_enemy_loader(): void
    {
        self::assertSame(0.5, (new ContactConfigLoader)->load(self::file()));
        self::assertCount(7, (new EnemyConfigLoader)->load(self::file())->all());
    }

    /** @return iterable<string, array{string, mixed, string}> */
    public static function invalidFields(): iterable
    {
        yield 'missing' => ['invulnerabilidadeSegundos', null, 'contato.invulnerabilidadeSegundos deve ser um número'];
        yield 'negative' => ['invulnerabilidadeSegundos', -0.1, 'contato.invulnerabilidadeSegundos deve ser maior ou igual a zero'];
        yield 'unknown' => ['knockback', 10, 'contato.knockback é um campo de gameplay desconhecido'];
        yield 'missing source' => ['_fonte', null, 'contato._fonte deve ser uma string'];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_field_is_reported(string $field, mixed $value, string $message): void
    {
        $root = ConfigSchema::object(ConfigJsonReader::load(self::file()), 'test');
        $section = ConfigSchema::object($root->contato, 'test');
        if ($value === null) {
            unset($section->{$field});
        } else {
            $section->{$field} = $value;
        }

        $this->expectExceptionMessage($message);

        (new ContactConfigLoader)->decode(json_encode($root, JSON_THROW_ON_ERROR));
    }

    private static function file(): string
    {
        return dirname(__DIR__, 4).'/config/enemies.json';
    }
}
