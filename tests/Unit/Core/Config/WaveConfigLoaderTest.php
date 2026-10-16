<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Core\Config;

use InvalidArgumentException;
use Jogo\Core\Config\ConfigJsonReader;
use Jogo\Core\Config\ConfigSchema;
use Jogo\Core\Config\EnemyConfigLoader;
use Jogo\Core\Config\WaveConfigLoader;
use Jogo\Domain\Wave\WaveBand;
use Jogo\Domain\Wave\WaveSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class WaveConfigLoaderTest extends TestCase
{
    public function test_real_curve_is_loaded(): void
    {
        $settings = self::loader()->load(self::file());

        self::assertSame(300, $settings->maxAlive);
        self::assertSame([0.04, 0.03], [$settings->healthPerLevel, $settings->damagePerLevel]);
        self::assertSame([
            [0.0, 120.0, 2.0, 2, ['spider']],
            [120.0, 300.0, 1.5, 3, ['spider', 'zombie']],
            [300.0, 480.0, 1.1, 4, ['zombie', 'slime', 'skeleton']],
            [480.0, 600.0, 0.8, 5, ['zombie', 'husk', 'slime', 'skeleton']],
            [600.0, null, 0.7, 6, ['zombie', 'husk', 'slime', 'skeleton']],
        ], array_map(static fn (WaveBand $b) => [$b->fromSeconds, $b->toSeconds, $b->intervalSeconds, $b->perSpawn,
            $b->typeIds], $settings->bands));
    }

    public function test_band_lookup_respects_the_edges(): void
    {
        $settings = self::loader()->load(self::file());

        self::assertSame(0, $settings->bandIndexAt(0.0));
        self::assertSame(0, $settings->bandIndexAt(119.999));
        self::assertSame(1, $settings->bandIndexAt(120.0));
        self::assertSame(4, $settings->bandIndexAt(600.0));
        self::assertSame(4, $settings->bandIndexAt(5000.0));
    }

    public function test_level_scaling(): void
    {
        $settings = self::loader()->load(self::file());

        self::assertSame(1.0, $settings->healthMultiplier(1));
        self::assertEqualsWithDelta(1.4, $settings->healthMultiplier(11), 1e-12);
        self::assertEqualsWithDelta(1.3, $settings->damageMultiplier(11), 1e-12);
    }

    /** @return iterable<string, array{callable(stdClass): void, string}> */
    public static function invalidFiles(): iterable
    {
        yield 'unknown type' => [static function (stdClass $r): void {
            self::band($r, 0)->tipos = ['creeper'];
        }, 'faixas[0].tipos[0] não é um inimigo conhecido'];
        yield 'boss in a wave' => [static function (stdClass $r): void {
            self::band($r, 0)->tipos = ['warden'];
        }, '(warden) não surge por onda'];
        yield 'split child in a wave' => [static function (stdClass $r): void {
            self::band($r, 0)->tipos = ['slime_pequeno'];
        }, '(slime_pequeno) não surge por onda'];
        yield 'gap between bands' => [static function (stdClass $r): void {
            self::band($r, 1)->deSegundos = 130;
        }, 'faixa 1 deveria começar em 120'];
        yield 'closed last band' => [static function (stdClass $r): void {
            self::band($r, 4)->ateSegundos = 900;
        }, 'só a última faixa pode ficar sem fim'];
        yield 'missing end' => [static function (stdClass $r): void {
            unset(self::band($r, 2)->ateSegundos);
        }, 'faixas[2].ateSegundos é obrigatório'];
        yield 'zero interval' => [static function (stdClass $r): void {
            self::band($r, 0)->intervaloSpawnSegundos = 0;
        }, 'intervalSeconds deve ser maior que zero'];
        yield 'fractional count' => [static function (stdClass $r): void {
            self::band($r, 0)->inimigosPorSpawn = 1.5;
        }, 'inimigosPorSpawn deve ser inteiro'];
        yield 'adaptive director on' => [static function (stdClass $r): void {
            $r->diretorAdaptativo = true;
        }, 'diretorAdaptativo deve ser false'];
        yield 'flat scaling' => [static function (stdClass $r): void {
            $scaling = ConfigSchema::object($r->escalonamentoPorNivel, 'test');
            ConfigSchema::object($scaling->vidaPorNivel, 'test')->tipo = 'FLAT';
        }, 'vidaPorNivel.tipo deve ser PERCENT'];
        yield 'fractional cap' => [static function (stdClass $r): void {
            ConfigSchema::object($r->limites, 'test')->inimigosSimultaneosMaximo = 300.5;
        }, 'inimigosSimultaneosMaximo deve ser inteiro'];
    }

    /** @param callable(stdClass): void $change */
    #[DataProvider('invalidFiles')]
    public function test_invalid_file_is_reported(callable $change, string $message): void
    {
        $root = ConfigSchema::object(ConfigJsonReader::load(self::file()), 'test');
        $change($root);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        self::loader()->decode(json_encode($root, JSON_THROW_ON_ERROR));
    }

    public function test_settings_need_at_least_one_band(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WaveSettings([], 300, 0.04, 0.03);
    }

    private static function band(stdClass $root, int $index): stdClass
    {
        return ConfigSchema::object(ConfigSchema::list($root->faixas, 'test')[$index], 'test');
    }

    private static function loader(): WaveConfigLoader
    {
        return new WaveConfigLoader((new EnemyConfigLoader)->load(dirname(__DIR__, 4).'/config/enemies.json'));
    }

    private static function file(): string
    {
        return dirname(__DIR__, 4).'/config/waves.json';
    }
}
