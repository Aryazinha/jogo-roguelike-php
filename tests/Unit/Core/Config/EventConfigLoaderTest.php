<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Core\Config;

use InvalidArgumentException;
use Jogo\Core\Config\ConfigJsonReader;
use Jogo\Core\Config\ConfigSchema;
use Jogo\Core\Config\EventConfigLoader;
use Jogo\Domain\RandomEvent\EventEffect;
use Jogo\Domain\RandomEvent\RandomEventDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class EventConfigLoaderTest extends TestCase
{
    public function test_real_file_has_the_three_events(): void
    {
        $settings = (new EventConfigLoader)->load(self::file());

        self::assertSame([60, 120], [$settings->minGapSeconds, $settings->maxGapSeconds]);
        self::assertSame([
            ['horda', 40, 20.0, [['inimigosPorSpawn', 2.0], ['intervaloSpawnSegundos', -0.5]]],
            ['chuva_de_xp', 40, 30.0, [['xpDropado', 1.0]]],
            ['tesouro', 20, 45.0, [['chanceDeBau', 1.5]]],
        ], array_map(static fn (RandomEventDefinition $e) => [$e->id, $e->weight, $e->durationSeconds,
            array_map(static fn (EventEffect $f) => [$f->attribute, $f->percent], $e->effects)], $settings->events));
        self::assertSame(100, $settings->totalWeight());
    }

    /** @return iterable<string, array{callable(stdClass): void, string}> */
    public static function invalidFiles(): iterable
    {
        yield 'only one event' => [static function (stdClass $r): void {
            $events = ConfigSchema::object($r->eventos, 'test');
            unset($events->chuva_de_xp, $events->tesouro);
        }, 'RF35 exige pelo menos 2'];
        yield 'zero weight' => [static function (stdClass $r): void {
            self::event($r, 'horda')->peso = 0;
        }, 'weight deve ser inteiro maior ou igual a um'];
        yield 'fractional weight' => [static function (stdClass $r): void {
            self::event($r, 'horda')->peso = 2.5;
        }, 'horda.peso deve ser inteiro'];
        yield 'unknown effect' => [static function (stdClass $r): void {
            $effect = ConfigSchema::list(self::event($r, 'tesouro')->efeitos, 'test')[0];
            ConfigSchema::object($effect, 'test')->atributo = 'ouro';
        }, 'atributo de evento desconhecido: ouro'];
        yield 'interval reduced to zero' => [static function (stdClass $r): void {
            $effect = ConfigSchema::list(self::event($r, 'horda')->efeitos, 'test')[1];
            ConfigSchema::object($effect, 'test')->valor = -1.0;
        }, 'percent deve ser finito e maior que -1'];
        yield 'flat effect' => [static function (stdClass $r): void {
            $effect = ConfigSchema::list(self::event($r, 'chuva_de_xp')->efeitos, 'test')[0];
            ConfigSchema::object($effect, 'test')->tipo = 'FLAT';
        }, 'tipo deve ser PERCENT'];
        yield 'gap inverted' => [static function (stdClass $r): void {
            ConfigSchema::object($r->intervaloEntreEventosSegundos, 'test')->max = 30;
        }, 'maxGapSeconds deve ser maior ou igual'];
        yield 'missing source' => [static function (stdClass $r): void {
            unset(self::event($r, 'horda')->_fonte);
        }, 'horda._fonte deve ser uma string'];
    }

    /** @param callable(stdClass): void $change */
    #[DataProvider('invalidFiles')]
    public function test_invalid_file_is_reported(callable $change, string $message): void
    {
        $root = ConfigSchema::object(ConfigJsonReader::load(self::file()), 'test');
        $change($root);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new EventConfigLoader)->decode(json_encode($root, JSON_THROW_ON_ERROR));
    }

    private static function event(stdClass $root, string $id): stdClass
    {
        return ConfigSchema::object(ConfigSchema::object($root->eventos, 'test')->{$id}, 'test');
    }

    private static function file(): string
    {
        return dirname(__DIR__, 4).'/config/events.json';
    }
}
