<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Core\Config;

use InvalidArgumentException;
use Jogo\Core\Config\ConfigJsonReader;
use Jogo\Core\Config\ConfigSchema;
use Jogo\Core\Config\SceneryConfigLoader;
use Jogo\Domain\Scenery\BreakableType;
use Jogo\Domain\Scenery\DropChance;
use Jogo\Domain\Scenery\TemporaryBonus;
use Jogo\Domain\Value\StatModifier;
use Jogo\Tests\Support\ControlledRandomIndex;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class SceneryConfigLoaderTest extends TestCase
{
    public function test_real_file_is_loaded(): void
    {
        $settings = (new SceneryConfigLoader)->load(self::file());

        self::assertSame(8.0, $settings->density);
        // Mapa útil de 3936 × 3936 px: cerca de 124 elementos.
        self::assertSame(124, $settings->countFor(3936.0 * 3936.0));
        self::assertSame([
            ['grass', 1.0, 12.0, false, [['nenhum', 0.7], ['steak', 0.2], ['xp_pequeno', 0.1]]],
            ['crate', 3.0, 18.0, true, [['steak', 0.45], ['bonus_temporario', 0.35], ['item_especial', 0.2]]],
            ['ore', 8.0, 20.0, true, [['item_especial', 0.6], ['bonus_temporario', 0.4]]],
        ], array_map(static fn (BreakableType $t) => [$t->id, $t->maxHealth, $t->collisionRadius, $t->blocksMovement,
            array_map(static fn (DropChance $d) => [$d->itemId, $d->probability], $t->drops)], $settings->types()));
        self::assertSame([
            ['furia', 15.0, [['dano', 'PERCENT', 0.5]]],
            ['pressa', 15.0, [['cooldown', 'PERCENT', -0.3]]],
            ['agilidade', 15.0, [['velocidadeJogador', 'PERCENT', 0.3]]],
        ], array_map(static fn (TemporaryBonus $b) => [$b->id, $b->durationSeconds, array_map(
            static fn (StatModifier $m) => [$m->attribute, $m->type->value, $m->value], $b->modifiers)], $settings->bonuses()));
    }

    public function test_drop_table_limits(): void
    {
        $grass = (new SceneryConfigLoader)->load(self::file())->type('grass');

        // 0-6999 nada, 7000-8999 Steak, 9000-9999 XP pequeno.
        self::assertSame('nenhum', $grass->drawDrop(new ControlledRandomIndex(6999)));
        self::assertSame('steak', $grass->drawDrop(new ControlledRandomIndex(7000)));
        self::assertSame('steak', $grass->drawDrop(new ControlledRandomIndex(8999)));
        self::assertSame('xp_pequeno', $grass->drawDrop(new ControlledRandomIndex(9000)));
    }

    /** @return iterable<string, array{callable(stdClass): void, string}> */
    public static function invalidFiles(): iterable
    {
        yield 'drops not summing one' => [static function (stdClass $r): void {
            self::drop($r, 'grass', 0)->probabilidade = 0.6;
        }, 'devem somar 1'];
        yield 'unknown item' => [static function (stdClass $r): void {
            self::drop($r, 'ore', 0)->item = 'diamante';
        }, 'item de drop desconhecido: diamante'];
        yield 'repeated item' => [static function (stdClass $r): void {
            self::drop($r, 'ore', 1)->item = 'item_especial';
        }, 'item repetido na tabela de drops'];
        yield 'block as text' => [static function (stdClass $r): void {
            self::element($r, 'crate')->bloqueiaMovimento = 'sim';
        }, 'crate.bloqueiaMovimento deve ser true ou false'];
        yield 'zero health' => [static function (stdClass $r): void {
            self::element($r, 'crate')->vida = 0;
        }, 'maxHealth deve ser maior que zero'];
        yield 'unknown bonus attribute' => [static function (stdClass $r): void {
            $bonus = ConfigSchema::object(ConfigSchema::object($r->bonusTemporarios, 'test')->furia, 'test');
            ConfigSchema::object(ConfigSchema::list($bonus->modificadores, 'test')[0], 'test')->atributo = 'sorte';
        }, 'Atributo desconhecido: sorte'];
    }

    /** @param callable(stdClass): void $change */
    #[DataProvider('invalidFiles')]
    public function test_invalid_file_is_reported(callable $change, string $message): void
    {
        $root = ConfigSchema::object(ConfigJsonReader::load(self::file()), 'test');
        $change($root);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new SceneryConfigLoader)->decode(json_encode($root, JSON_THROW_ON_ERROR));
    }

    private static function element(stdClass $root, string $id): stdClass
    {
        return ConfigSchema::object(ConfigSchema::object($root->elementos, 'test')->{$id}, 'test');
    }

    private static function drop(stdClass $root, string $id, int $index): stdClass
    {
        return ConfigSchema::object(ConfigSchema::list(self::element($root, $id)->drops, 'test')[$index], 'test');
    }

    private static function file(): string
    {
        return dirname(__DIR__, 4).'/config/scenery.json';
    }
}
