<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Core\Config;

use InvalidArgumentException;
use Jogo\Core\Config\ConfigJsonReader;
use Jogo\Core\Config\ConfigSchema;
use Jogo\Core\Config\EnemyConfigLoader;
use Jogo\Domain\Enemy\EnemyArchetype;
use Jogo\Domain\Enemy\EnemyCatalog;
use Jogo\Domain\Enemy\EnemyCategory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class EnemyConfigLoaderTest extends TestCase
{
    public function test_real_file_loads_every_enemy(): void
    {
        $catalog = self::realCatalog();

        self::assertSame(['zombie', 'spider', 'husk', 'slime', 'slime_pequeno', 'skeleton', 'warden'],
            array_map(static fn (EnemyArchetype $a) => $a->id, $catalog->all()));
        self::assertCount(3, $catalog->byCategory(EnemyCategory::COMMON));
        self::assertCount(3, $catalog->byCategory(EnemyCategory::SPECIAL));
        self::assertSame('warden', $catalog->boss()->id);
    }

    public function test_zombie_keeps_the_values_from_the_requirements(): void
    {
        // RF22: vida 13, dano 3, XP 3-4, aparece do nível 6 em diante.
        $zombie = self::realCatalog()->get('zombie');

        self::assertSame(13.0, $zombie->maxHealth);
        self::assertSame(3.0, $zombie->damage);
        self::assertSame([3, 4], [$zombie->xpMin, $zombie->xpMax]);
        self::assertSame(6, $zombie->minLevel);
    }

    public function test_every_number_in_the_file_reaches_the_archetype(): void
    {
        $catalog = self::realCatalog();
        $root = self::raw();

        foreach (EnemyCategory::cases() as $category) {
            $group = ConfigSchema::object($root->{$category->value}, 'test');
            foreach (get_object_vars($group) as $id => $raw) {
                $entry = ConfigSchema::object($raw, 'test');
                $xp = ConfigSchema::object($entry->xp, 'test');
                $archetype = $catalog->get((string) $id);

                self::assertSame($category, $archetype->category, (string) $id);
                self::assertSame($entry->nome, $archetype->name);
                self::assertSame(ConfigSchema::number($entry, 'vida', 'test'), $archetype->maxHealth);
                self::assertSame(ConfigSchema::number($entry, 'dano', 'test'), $archetype->damage);
                self::assertSame([$xp->min, $xp->max], [$archetype->xpMin, $archetype->xpMax]);
                self::assertSame(ConfigSchema::number($entry, 'velocidade', 'test'), $archetype->speed);
                self::assertSame(ConfigSchema::number($entry, 'raioColisao', 'test'), $archetype->collisionRadius);
                self::assertSame($entry->comportamento, $archetype->behaviorId);
                self::assertSame($entry->nivelMinimoAparicao ?? null, $archetype->minLevel);
            }
        }
    }

    public function test_special_rules_are_mapped(): void
    {
        $catalog = self::realCatalog();

        $split = $catalog->get('slime')->split;
        self::assertNotNull($split);
        self::assertSame(['slime_pequeno', 2], [$split->childTypeId, $split->count]);
        self::assertTrue($catalog->get('slime_pequeno')->onlyFromSplit);

        $ranged = $catalog->get('skeleton')->ranged;
        self::assertNotNull($ranged);
        self::assertSame([200.0, 40.0, 2.2, 260.0, 4.0], [$ranged->idealDistance, $ranged->hysteresis,
            $ranged->shotCooldownSeconds, $ranged->projectileSpeed, $ranged->projectileRadius]);
        self::assertSame(360.0, $ranged->projectileRange());

        $warden = $catalog->boss();
        self::assertSame(600.0, $warden->spawnAtSeconds);
        self::assertNull($warden->minLevel);
    }

    /** @return iterable<string, array{string, string, string, mixed, string}> */
    public static function invalidFields(): iterable
    {
        yield 'missing health' => ['comuns', 'zombie', 'vida', null, 'zombie.vida deve ser um número'];
        yield 'text health' => ['comuns', 'zombie', 'vida', '13', 'zombie.vida deve ser um número'];
        yield 'zero health' => ['comuns', 'spider', 'vida', 0, 'spider: vida deve ser maior que zero'];
        yield 'negative damage' => ['comuns', 'husk', 'dano', -1, 'husk: dano deve ser finito'];
        yield 'xp not object' => ['comuns', 'zombie', 'xp', 3, 'zombie.xp deve ser um objeto'];
        yield 'unknown field' => ['comuns', 'zombie', 'voa', true, 'zombie.voa é um campo de gameplay desconhecido'];
        yield 'missing source' => ['comuns', 'spider', '_fonte', null, 'spider._fonte deve ser uma string'];
        yield 'bad source' => ['comuns', 'spider', '_fonte', 'palpite', 'spider._fonte deve ser enunciado ou provisorio'];
        yield 'level zero' => ['comuns', 'zombie', 'nivelMinimoAparicao', 0, 'zombie.nivelMinimoAparicao deve ser um inteiro >= 1'];
        yield 'missing level' => ['comuns', 'zombie', 'nivelMinimoAparicao', null, 'zombie: nivelMinimoAparicao deve ser inteiro'];
        yield 'unknown behavior' => ['comuns', 'zombie', 'comportamento', 'voar', 'zombie: comportamento desconhecido: voar'];
        yield 'split on common' => ['comuns', 'zombie', 'aoMorrer', (object) ['gera' => 'spider', 'quantidade' => 2], 'zombie.aoMorrer é um campo'];
        yield 'split to nothing' => ['especiais', 'slime', 'aoMorrer', (object) ['gera' => 'creeper', 'quantidade' => 2], 'slime se divide em um inimigo inexistente'];
        yield 'split zero' => ['especiais', 'slime', 'aoMorrer', (object) ['gera' => 'slime_pequeno', 'quantidade' => 0], 'slime: aoMorrer.quantidade da divisão'];
        yield 'flag as text' => ['especiais', 'slime_pequeno', 'geradoApenasPorDivisao', 'sim', 'geradoApenasPorDivisao deve ser true ou false'];
        yield 'arrow without speed' => ['especiais', 'skeleton', 'velocidadeFlechaPixels', null, 'skeleton.velocidadeFlechaPixels deve ser um número'];
        yield 'arrow zero radius' => ['especiais', 'skeleton', 'raioFlecha', 0, 'skeleton: raioFlecha deve ser maior que zero'];
        yield 'ranged incomplete' => ['especiais', 'skeleton', 'margemHisterese', null, 'skeleton.margemHisterese deve ser um número'];
        yield 'ranged on chaser' => ['comuns', 'spider', 'distanciaIdeal', 100, 'spider.margemHisterese deve ser um número'];
        yield 'boss without phases' => ['boss', 'warden', 'fases', null, 'warden.fases deve ser um objeto'];
        yield 'boss with level' => ['boss', 'warden', 'nivelMinimoAparicao', 1, 'warden.nivelMinimoAparicao é um campo'];
        yield 'boss without spawn' => ['boss', 'warden', 'surgeAosSegundos', null, 'warden.surgeAosSegundos deve ser um número'];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_field_is_reported_with_its_path(string $group, string $id, string $field, mixed $value, string $message): void
    {
        $root = self::raw();
        $entry = ConfigSchema::object(ConfigSchema::object($root->{$group}, 'test')->{$id}, 'test');
        if ($value === null) {
            unset($entry->{$field});
        } else {
            $entry->{$field} = $value;
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new EnemyConfigLoader)->decode(json_encode($root, JSON_THROW_ON_ERROR));
    }

    public function test_same_id_in_two_groups_is_rejected(): void
    {
        $root = self::raw();
        $groups = ConfigSchema::object($root->especiais, 'test');
        $groups->zombie = clone ConfigSchema::object($groups->slime, 'test');

        $this->expectExceptionMessage('enemies.json: ID de inimigo duplicado: zombie.');

        (new EnemyConfigLoader)->decode(json_encode($root, JSON_THROW_ON_ERROR));
    }

    public function test_repeated_key_in_the_text_is_not_silently_overwritten(): void
    {
        $json = (string) file_get_contents(self::file());
        $json = preg_replace('/"spider": \{/', '"zombie": {', $json, 1);
        self::assertIsString($json);

        $this->expectExceptionMessage('comuns.zombie contém chave/ID duplicado');

        (new EnemyConfigLoader)->decode($json);
    }

    public function test_root_and_meta_are_validated(): void
    {
        $root = self::raw();
        $meta = ConfigSchema::object($root->_meta, 'test');
        $meta->arquivo = 'waves.json';

        try {
            (new EnemyConfigLoader)->decode(json_encode($root, JSON_THROW_ON_ERROR));
            self::fail('_meta.arquivo errado deveria falhar.');
        } catch (InvalidArgumentException $error) {
            self::assertSame('enemies.json: _meta.arquivo deve ser enemies.json.', $error->getMessage());
        }

        $root = self::raw();
        $root->minibosses = new stdClass;
        $this->expectExceptionMessage('enemies.json.minibosses é um campo de gameplay desconhecido');
        (new EnemyConfigLoader)->decode(json_encode($root, JSON_THROW_ON_ERROR));
    }

    public function test_missing_file_is_reported(): void
    {
        $this->expectExceptionMessage('arquivo inexistente');

        (new EnemyConfigLoader)->load(__DIR__.'/nao-existe.json');
    }

    private static function realCatalog(): EnemyCatalog
    {
        return (new EnemyConfigLoader)->load(self::file());
    }

    private static function raw(): stdClass
    {
        return ConfigSchema::object(ConfigJsonReader::load(self::file()), 'enemies.json');
    }

    private static function file(): string
    {
        return dirname(__DIR__, 4).'/config/enemies.json';
    }
}
