<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Core\Config;

use Jogo\Core\Config\ConfigJsonReader;
use Jogo\Core\Config\ConfigSchema;
use Jogo\Core\Config\EnemyConfigLoader;
use Jogo\Core\Config\SteeringConfigLoader;
use Jogo\Domain\Ai\Behavior\WeightedBehavior;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SteeringConfigLoaderTest extends TestCase
{
    public function test_real_file_is_loaded_and_still_accepted_by_the_enemy_loader(): void
    {
        $settings = (new SteeringConfigLoader)->load(self::file());

        self::assertSame([40.0, 1.0, 1.4], [$settings->separationRadius, $settings->seekWeight, $settings->separationWeight]);
        self::assertInstanceOf(WeightedBehavior::class, $settings->chase());
        self::assertCount(7, (new EnemyConfigLoader)->load(self::file())->all());
    }

    /** @return iterable<string, array{string, mixed, string}> */
    public static function invalidFields(): iterable
    {
        yield 'missing radius' => ['raioPixels', null, 'separacao.raioPixels deve ser um número'];
        yield 'zero radius' => ['raioPixels', 0, 'separacao: raioPixels deve ser maior que zero'];
        yield 'negative weight' => ['pesoSeparacao', -1, 'separacao: pesoSeparacao deve ser finito'];
        yield 'unknown field' => ['inercia', 1, 'separacao.inercia é um campo de gameplay desconhecido'];
        yield 'missing source' => ['_fonte', null, 'separacao._fonte deve ser uma string'];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_field_is_reported(string $field, mixed $value, string $message): void
    {
        $root = ConfigSchema::object(ConfigJsonReader::load(self::file()), 'test');
        $section = ConfigSchema::object($root->separacao, 'test');
        if ($value === null) {
            unset($section->{$field});
        } else {
            $section->{$field} = $value;
        }

        $this->expectExceptionMessage($message);

        (new SteeringConfigLoader)->decode(json_encode($root, JSON_THROW_ON_ERROR));
    }

    public function test_missing_section_is_reported(): void
    {
        $root = ConfigSchema::object(ConfigJsonReader::load(self::file()), 'test');
        unset($root->separacao);

        $this->expectExceptionMessage('enemies.json: separacao deve ser um objeto');

        (new SteeringConfigLoader)->decode(json_encode($root, JSON_THROW_ON_ERROR));
    }

    private static function file(): string
    {
        return dirname(__DIR__, 4).'/config/enemies.json';
    }
}
