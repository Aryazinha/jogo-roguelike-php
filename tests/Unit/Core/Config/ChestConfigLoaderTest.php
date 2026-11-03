<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Core\Config;

use InvalidArgumentException;
use Jogo\Core\Config\ChestConfigLoader;
use Jogo\Core\Config\ConfigJsonReader;
use Jogo\Core\Config\ConfigSchema;
use Jogo\Domain\Chest\ChestRarity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ChestConfigLoaderTest extends TestCase
{
    public function test_real_file_keeps_the_requirement_values(): void
    {
        $settings = (new ChestConfigLoader)->load(self::file());

        // RF26: madeira 85% com 1 upgrade e bronze 10% com 2; ouro é acréscimo da equipe.
        self::assertSame([['madeira', 0.85, 'upgrade_de_arma', 1], ['bronze', 0.10, 'upgrade_de_arma', 2],
            ['ouro', 0.05, 'upgrade_de_arma', 3]], array_map(static fn (ChestRarity $r) => [$r->id, $r->probability,
                $r->rewardType, $r->rewardQuantity], $settings->rarities));
        self::assertSame([1, 0.004], [$settings->chestsOnBossDefeat, $settings->chancePerKill]);
        self::assertSame(['steak', 5.0], [$settings->healing->id, $settings->healing->heal]);
    }

    /** @return iterable<string, array{callable(stdClass): void, string}> */
    public static function invalidFiles(): iterable
    {
        yield 'sum below one' => [static function (stdClass $r): void {
            self::rarity($r, 2)->probabilidade = 0.04;
        }, 'devem somar 1'];
        yield 'too many decimals' => [static function (stdClass $r): void {
            self::rarity($r, 1)->probabilidade = 0.09995;
            self::rarity($r, 2)->probabilidade = 0.05005;
        }, 'mais de 4 casas decimais'];
        yield 'repeated rarity' => [static function (stdClass $r): void {
            self::rarity($r, 2)->id = 'bronze';
        }, 'raridade repetida: bronze'];
        yield 'no boss chest' => [static function (stdClass $r): void {
            ConfigSchema::object($r->geracao, 'test')->bausAoDerrotarBoss = 0;
        }, 'pelo menos um baú (RF26)'];
        yield 'chance above one' => [static function (stdClass $r): void {
            ConfigSchema::object($r->geracao, 'test')->chancePorInimigoAbatido = 1.5;
        }, 'chancePerKill deve ser no máximo 1'];
        yield 'zero reward' => [static function (stdClass $r): void {
            ConfigSchema::object(self::rarity($r, 0)->recompensa, 'test')->quantidade = 0;
        }, 'rewardQuantity deve ser maior ou igual a um'];
        yield 'missing source' => [static function (stdClass $r): void {
            unset(self::rarity($r, 0)->_fonte);
        }, 'raridades[0]._fonte deve ser uma string'];
        yield 'zero heal' => [static function (stdClass $r): void {
            ConfigSchema::object($r->cura, 'test')->cura = 0;
        }, 'heal deve ser maior que zero'];
    }

    /** @param callable(stdClass): void $change */
    #[DataProvider('invalidFiles')]
    public function test_invalid_file_is_reported(callable $change, string $message): void
    {
        $root = ConfigSchema::object(ConfigJsonReader::load(self::file()), 'test');
        $change($root);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new ChestConfigLoader)->decode(json_encode($root, JSON_THROW_ON_ERROR));
    }

    private static function rarity(stdClass $root, int $index): stdClass
    {
        return ConfigSchema::object(ConfigSchema::list($root->raridades, 'test')[$index], 'test');
    }

    private static function file(): string
    {
        return dirname(__DIR__, 4).'/config/chests.json';
    }
}
