<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Core\Config;

use InvalidArgumentException;
use Jogo\Core\Config\WeaponConfigLoader;
use Jogo\Core\Config\WeaponDefinitionMapper;
use Jogo\Domain\Weapon\WeaponDefinition;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class WeaponConfigLoaderTest extends TestCase
{
    public function test_real_four_definitions_are_mapped_exactly_without_changing_the_file(): void
    {
        $path = dirname(__DIR__, 4).'/config/weapons.json';
        $before = file_get_contents($path);
        self::assertIsString($before);
        $catalog = (new WeaponConfigLoader)->load($path);
        self::assertSame(4, $catalog->count());
        self::assertSame(['stone_sword', 'bow', 'tnt', 'torch'], array_map(static fn (WeaponDefinition $definition): string => $definition->id, $catalog->all()));
        $expected = [
            'stone_sword' => ['Stone Sword', 2.5, 2.0, 'direcao_do_olhar', 'meia_lua', 90.0, 120.0, null, null, null, null],
            'bow' => ['Bow and Arrow', 3.2, 1.8, 'inimigo_mais_proximo', 'projetil', 320.0, null, 1, 420.0, null, null],
            'tnt' => ['TNT', 6.0, 4.0, 'inimigo_aleatorio', 'area', 260.0, null, null, null, 110.0, 1.0],
            'torch' => ['Torch', 1.2, 0.8, 'area_ao_redor', 'pulso', 70.0, null, null, null, null, null],
        ];
        foreach ($expected as $id => $values) {
            $definition = $catalog->get($id);
            self::assertSame($id, $definition->id);
            self::assertSame($values, [$definition->name, $definition->damage, $definition->cooldownSeconds,
                $definition->targetingId, $definition->shapeId, $definition->range, $definition->angleDegrees,
                $definition->projectileCount, $definition->projectileSpeed, $definition->explosionRadius, $definition->detonationDelaySeconds]);
            self::assertArrayNotHasKey('_fonte', get_object_vars($definition));
            self::assertArrayNotHasKey('_nota', get_object_vars($definition));
        }
        self::assertSame($before, file_get_contents($path));
    }

    /** @return iterable<string, array{string}> */
    public static function malformedJson(): iterable
    {
        yield 'truncated' => ['{"armas":'];
        yield 'trailing comma' => ['{"armas":{},}'];
        yield 'invalid utf8' => ["{\"nota\":\"\xFF\"}"];
    }

    #[DataProvider('malformedJson')]
    public function test_malformed_json_identifies_the_source_file(string $json): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('fixture/weapons.json: JSON malformado');
        (new WeaponConfigLoader)->decode($json, 'fixture/weapons.json');
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function invalidDocuments(): iterable
    {
        foreach (['null' => null, 'bool' => true, 'string' => 'invalid', 'number' => 1, 'array' => []] as $label => $root) {
            yield "root:$label" => [$root, 'raiz'];
        }
        yield 'missing meta' => [(object) ['armas' => (object) []], '_meta'];
        foreach (['null' => null, 'array' => [], 'missing fields' => (object) []] as $label => $meta) {
            [$root] = self::document();
            $root->_meta = $meta;
            yield "meta:$label" => [$root, '_meta'];
        }
        foreach (['arquivo' => 'other.json', 'nota' => 1, 'requisitos' => (object) ['RF18' => true]] as $field => $value) {
            [$root] = self::document();
            $root->_meta = (object) ['arquivo' => 'weapons.json', 'nota' => 'Test', 'requisitos' => ['RF18']];
            self::assertInstanceOf(stdClass::class, $root->_meta);
            $root->_meta->{$field} = $value;
            yield "meta:$field" => [$root, "_meta.$field"];
        }
        [$root] = self::document();
        $root->_meta = (object) ['arquivo' => 'weapons.json', 'nota' => 'Test', 'requisitos' => [1]];
        yield 'invalid requirement id' => [$root, '_meta.requisitos'];
        foreach (['null' => null, 'array' => [], 'empty object' => (object) [], 'list' => [(object) []]] as $label => $weapons) {
            [$root] = self::document();
            $root->armas = $weapons;
            yield "weapons:$label" => [$root, 'armas'];
        }
        [$root] = self::document();
        $root->armas = (object) ['test' => []];
        yield 'weapon is not object' => [$root, 'test'];
        [$root] = self::document();
        $root->unknown = 1;
        yield 'unknown root gameplay' => [$root, 'unknown'];
    }

    #[DataProvider('invalidDocuments')]
    public function test_invalid_root_metadata_and_collection_are_rejected(mixed $root, string $field): void
    {
        try {
            (new WeaponConfigLoader)->decode(json_encode($root, JSON_THROW_ON_ERROR), 'fixture/weapons.json');
            self::fail('Documento inválido deve falhar.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('fixture/weapons.json', $exception->getMessage());
            self::assertStringContainsString($field, $exception->getMessage());
        }
    }

    /** @return iterable<string, array{string, string, mixed, bool}> */
    public static function invalidWeaponFields(): iterable
    {
        foreach (['nome', 'dano', 'cooldownSegundos', 'mira', 'formato', 'alcance', '_fonte', '_nota'] as $field) {
            yield "$field:missing" => ['pulso', $field, null, true];
            yield "$field:wrong type" => ['pulso', $field, [], false];
        }
        foreach (['nome', 'mira', 'formato'] as $field) {
            yield "$field:blank" => ['pulso', $field, ' ', false];
        }
        $numeric = ['dano' => 'pulso', 'cooldownSegundos' => 'pulso', 'alcance' => 'pulso',
            'anguloGraus' => 'meia_lua', 'velocidadeProjetil' => 'projetil', 'raioExplosao' => 'area', 'atrasoDetonacaoSegundos' => 'area'];
        foreach ($numeric as $field => $shape) {
            foreach (['negative' => -1.0, 'inf' => INF, 'negative inf' => -INF, 'nan' => NAN, 'string' => '1.5', 'bool' => true] as $label => $value) {
                yield "$field:$label" => [$shape, $field, $value, false];
            }
            if (! in_array($field, ['dano', 'atrasoDetonacaoSegundos'], true)) {
                yield "$field:zero" => [$shape, $field, 0, false];
            }
        }
        foreach (['anguloGraus' => 'meia_lua', 'projeteis' => 'projetil', 'velocidadeProjetil' => 'projetil',
            'raioExplosao' => 'area', 'atrasoDetonacaoSegundos' => 'area'] as $field => $shape) {
            yield "$field:missing specific" => [$shape, $field, null, true];
            yield "$field:wrong shape" => ['pulso', $field, 1, false];
            yield "$field:null specific" => [$shape, $field, null, false];
        }
        foreach (['fraction' => 1.5, 'float one' => 1.0, 'string' => '1', 'bool' => true, 'zero' => 0, 'negative' => -1] as $label => $value) {
            yield "projeteis:$label" => ['projetil', 'projeteis', $value, false];
        }
        yield 'angle over 360' => ['meia_lua', 'anguloGraus', 361, false];
        yield 'unknown targeting' => ['pulso', 'mira', 'unknown', false];
        yield 'incompatible targeting' => ['projetil', 'mira', 'area_ao_redor', false];
        yield 'unknown shape' => ['pulso', 'formato', 'unknown', false];
        yield 'unknown gameplay field' => ['pulso', 'area', 1, false];
        yield 'unknown origin metadata' => ['pulso', '_fonte', 'invented', false];
    }

    #[DataProvider('invalidWeaponFields')]
    public function test_weapon_errors_identify_file_id_and_json_field(string $shape, string $field, mixed $value, bool $missing): void
    {
        [$root, $weapon] = self::document($shape);
        if ($missing) {
            unset($weapon->{$field});
        } else {
            $weapon->{$field} = $value;
        }
        try {
            (new WeaponDefinitionMapper)->map($root, 'fixture/weapons.json');
            self::fail('Campo inválido deve falhar.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('fixture/weapons.json', $exception->getMessage());
            self::assertStringContainsString('test', $exception->getMessage());
            self::assertStringContainsString($field, $exception->getMessage());
        }
    }

    public function test_additional_metadata_and_quoted_structure_in_notes_do_not_become_gameplay(): void
    {
        [$root, $weapon] = self::document();
        $root->_extra = (object) ['anything' => true];
        $weapon->_extra = ['test' => 'metadata'];
        $weapon->_nota = 'Texto: "armas": { "test": 1, "test": 2 }';
        $before = json_encode($root, JSON_THROW_ON_ERROR);
        $catalog = (new WeaponConfigLoader)->decode($before);
        self::assertSame(1, $catalog->count());
        self::assertSame('test', $catalog->get('test')->id);
        self::assertSame($before, json_encode($root, JSON_THROW_ON_ERROR));
    }

    public function test_duplicate_weapon_ids_including_escaped_keys_are_not_silently_replaced(): void
    {
        [, $weapon] = self::document();
        $encoded = json_encode($weapon, JSON_THROW_ON_ERROR);
        $json = '{"_meta":{"arquivo":"weapons.json","nota":"Test","requisitos":[]},"armas":{"test":'.$encoded.',"te\\u0073t":'.$encoded.'}}';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('weapons.json: armas.test');
        (new WeaponConfigLoader)->decode($json);
    }

    public function test_duplicate_gameplay_fields_identify_weapon_and_field(): void
    {
        [$root] = self::document();
        $json = str_replace('"dano":2.5', '"dano":2.5,"dano":99', json_encode($root, JSON_THROW_ON_ERROR));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('weapons.json: armas.test.dano');
        (new WeaponConfigLoader)->decode($json);
    }

    public function test_numeric_string_ids_are_preserved_as_content_ids(): void
    {
        [$root, $weapon] = self::document();
        $root->armas = (object) ['0' => $weapon, '10' => $weapon];
        $catalog = (new WeaponConfigLoader)->decode(json_encode($root, JSON_THROW_ON_ERROR));
        self::assertSame(['0', '10'], array_map(static fn (WeaponDefinition $definition): string => $definition->id, $catalog->all()));
    }

    public function test_loading_a_directory_fails_clearly_without_attempting_to_decode_it(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(__DIR__);
        (new WeaponConfigLoader)->load(__DIR__);
    }

    public function test_duplicate_key_scan_failure_is_reported_instead_of_accepting_unchecked_json(): void
    {
        $previous = ini_get('pcre.backtrack_limit');
        self::assertIsString($previous);
        [$root, $weapon] = self::document();
        // Com o PCRE JIT (padrão do PHP) o backtrack_limit é ignorado; a string longa esgota a
        // pilha do JIT. Sem JIT, esgota o backtrack_limit. A varredura falha nos dois modos.
        $weapon->nome = str_repeat('a', 2_000_000);
        $json = json_encode($root, JSON_THROW_ON_ERROR);
        ini_set('pcre.backtrack_limit', '1');
        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('weapons.json: não foi possível verificar chaves duplicadas');
            (new WeaponConfigLoader)->decode($json);
        } finally {
            ini_set('pcre.backtrack_limit', $previous);
        }
    }

    /** @return array{stdClass, stdClass} */
    private static function document(string $shape = 'pulso'): array
    {
        $specific = match ($shape) {
            'meia_lua' => ['anguloGraus' => 120],
            'projetil' => ['projeteis' => 1, 'velocidadeProjetil' => 420],
            'area' => ['raioExplosao' => 110, 'atrasoDetonacaoSegundos' => 1.0],
            default => [],
        };
        $weapon = (object) (['nome' => 'Test weapon', 'dano' => 2.5, 'cooldownSegundos' => 2.0,
            'mira' => WeaponDefinition::TARGETING_BY_SHAPE[$shape], 'formato' => $shape,
            'alcance' => 70, '_fonte' => 'provisorio', '_nota' => 'Dados apenas de teste.'] + $specific);
        $root = (object) ['_meta' => (object) ['arquivo' => 'weapons.json', 'requisitos' => ['RF18'], 'nota' => 'Test'],
            'armas' => (object) ['test' => $weapon]];

        return [$root, $weapon];
    }
}
