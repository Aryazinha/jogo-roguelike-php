<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Core\Config;

use InvalidArgumentException;
use Jogo\Core\Config\BossConfigLoader;
use Jogo\Core\Config\ConfigJsonReader;
use Jogo\Core\Config\ConfigSchema;
use Jogo\Core\Config\EnemyConfigLoader;
use Jogo\Domain\Enemy\Boss\BossPhaseSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class BossConfigLoaderTest extends TestCase
{
    public function test_real_phases_are_loaded(): void
    {
        $settings = self::loader()->load(self::file());

        self::assertSame([0.8, 1.0, 3.0, 1.0, 8.0], [$settings->telegraphSeconds, $settings->chargeSeconds,
            $settings->chargeSpeedMultiplier, $settings->recoverSeconds, $settings->chargeCooldownSeconds]);
        self::assertSame([0.7, 0.4], $settings->summonThresholds);
        self::assertSame([8, 'zombie', 1.0], [$settings->summonCount, $settings->summonTypeId, $settings->summonSeconds]);
        self::assertSame([0.25, 0.3, -0.3], [$settings->enrageThreshold, $settings->enragedSpeedBonus,
            $settings->enragedCooldownBonus]);
    }

    /** @return iterable<string, array{callable(stdClass): void, string}> */
    public static function invalidPhases(): iterable
    {
        yield 'missing telegraph' => [static function (stdClass $f): void {
            unset($f->telegrafando);
        }, 'fases.telegrafando deve ser um objeto'];
        yield 'unknown phase' => [static function (stdClass $f): void {
            $f->dancando = new stdClass;
        }, 'fases.dancando é um campo de gameplay desconhecido'];
        yield 'zero charge' => [static function (stdClass $f): void {
            self::section($f, 'investindo')->duracaoSegundos = 0;
        }, 'chargeSeconds deve ser maior que zero'];
        yield 'summon boss' => [static function (stdClass $f): void {
            self::section($f, 'invocando')->tipo = 'warden';
        }, 'invocando.tipo deve ser um inimigo comum existente: warden'];
        yield 'summon special' => [static function (stdClass $f): void {
            self::section($f, 'invocando')->tipo = 'slime';
        }, 'invocando.tipo deve ser um inimigo comum existente: slime'];
        yield 'threshold above one' => [static function (stdClass $f): void {
            self::section($f, 'invocando')->emVidaPercentual = [1.2];
        }, 'limiares de vida devem estar entre 0 e 1'];
        yield 'repeated threshold' => [static function (stdClass $f): void {
            self::section($f, 'invocando')->emVidaPercentual = [0.5, 0.5];
        }, 'não pode repetir limiar'];
        yield 'fractional count' => [static function (stdClass $f): void {
            self::section($f, 'invocando')->quantidade = 2.5;
        }, 'invocando.quantidade deve ser inteiro'];
        yield 'enrage flat' => [static function (stdClass $f): void {
            $modifier = self::section($f, 'enfurecido')->modificadores[0];
            self::assertInstanceOf(stdClass::class, $modifier);
            $modifier->tipo = 'FLAT';
        }, 'modificadores[0].tipo deve ser PERCENT'];
        yield 'enrage unknown attribute' => [static function (stdClass $f): void {
            $modifier = self::section($f, 'enfurecido')->modificadores[0];
            self::assertInstanceOf(stdClass::class, $modifier);
            $modifier->atributo = 'dano';
        }, 'atributo deve ser velocidade ou cooldown'];
    }

    /** @param callable(stdClass): void $change */
    #[DataProvider('invalidPhases')]
    public function test_invalid_phase_data_is_reported(callable $change, string $message): void
    {
        $root = ConfigSchema::object(ConfigJsonReader::load(self::file()), 'test');
        $warden = ConfigSchema::object(ConfigSchema::object($root->boss, 'test')->warden, 'test');
        $change(ConfigSchema::object($warden->fases, 'test'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        self::loader()->decode(json_encode($root, JSON_THROW_ON_ERROR));
    }

    public function test_settings_reject_cooldown_bonus_that_removes_the_wait(): void
    {
        $this->expectExceptionMessage('enragedCooldownBonus');

        new BossPhaseSettings(0.8, 1.0, 3.0, 1.0, 8.0, [0.7], 8, 'zombie', 1.0, 0.25, 0.3, -1.0);
    }

    private static function section(stdClass $phases, string $name): stdClass
    {
        return ConfigSchema::object($phases->{$name}, 'test');
    }

    private static function loader(): BossConfigLoader
    {
        return new BossConfigLoader((new EnemyConfigLoader)->load(self::file()));
    }

    private static function file(): string
    {
        return dirname(__DIR__, 4).'/config/enemies.json';
    }
}
