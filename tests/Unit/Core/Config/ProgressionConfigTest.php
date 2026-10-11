<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Core\Config;

use InvalidArgumentException;
use Jogo\Core\Config\ConfigJsonReader;
use Jogo\Core\Config\ConfigSchema;
use Jogo\Core\Config\EvolutionConfigLoader;
use Jogo\Core\Config\EvolutionDefinitionMapper;
use Jogo\Core\Config\UpgradeConfigLoader;
use Jogo\Core\Config\UpgradeDefinitionMapper;
use Jogo\Core\Config\WeaponConfigLoader;
use Jogo\Domain\Weapon\WeaponCatalog;
use Jogo\Tests\Support\ProgressionFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ProgressionConfigTest extends TestCase
{
    public function test_real_upgrades_and_evolutions_preserve_every_value_and_stable_sources(): void
    {
        $weapons = self::weapons();
        $upgrades = ProgressionFixtures::upgrades($weapons);
        self::assertCount(16, $upgrades->all());
        $root = self::raw('upgrades');
        $groups = ConfigSchema::object($root->upgrades, 'test');
        foreach (get_object_vars($groups) as $id => $levels) {
            foreach (ConfigSchema::list($levels, 'test') as $raw) {
                $entry = ConfigSchema::object($raw, 'test');
                $level = ConfigSchema::level($entry, 'nivel', 'test');
                $definition = $upgrades->get((string) $id, $level);
                self::assertSame($entry->descricao, $definition->description);
                foreach (ConfigSchema::list($entry->modificadores, 'test') as $index => $effect) {
                    $effect = ConfigSchema::object($effect, 'test');
                    $mapped = $definition->modifiers[$index];
                    self::assertSame($effect->atributo, $mapped->attribute);
                    self::assertSame($effect->tipo, $mapped->type->value);
                    self::assertSame(ConfigSchema::number($effect, 'valor', 'test'), $mapped->value);
                    self::assertSame("upgrades:{$id}:{$level}:{$index}", $mapped->source);
                }
            }
        }
        $evolutions = ProgressionFixtures::evolutions($weapons);
        self::assertCount(2, $evolutions->all());
        $sword = $evolutions->get('enchanted_stone_sword');
        self::assertSame('stone_sword', $sword->weaponId);
        self::assertSame('sharpness', $sword->passiveId);
        self::assertSame(5, $sword->weaponLevel);
        self::assertSame(3, $sword->passiveLevel);
        self::assertSame([9.0, 1.4, 140.0, 220.0], [$sword->definition->damage, $sword->definition->cooldownSeconds,
            $sword->definition->range, $sword->definition->angleDegrees]);
        $tnt = $evolutions->get('mega_tnt');
        self::assertSame('tnt', $tnt->weaponId);
        self::assertSame('gunpowder', $tnt->passiveId);
        self::assertSame([18.0, 3.2, 320.0, 220.0, 0.6], [$tnt->definition->damage,
            $tnt->definition->cooldownSeconds, $tnt->definition->range, $tnt->definition->explosionRadius,
            $tnt->definition->detonationDelaySeconds]);
    }

    public function test_all_three_json_files_are_byte_identical_to_the_previous_delivery(): void
    {
        $hashes = ['weapons' => '5c6f3045d93353923578df5cf3dae5479529129bb57139807bab598497e43610',
            'upgrades' => 'd134acebdd55ae1d7541660d24658c1eacafaea9fd46a8a46abbbf993f267cb8',
            'evolutions' => 'fecf952a85ba16f9bc880b5db4f3ddc98c6cd018720cbd0ba5b4a68090c86466'];
        foreach ($hashes as $file => $hash) {
            self::assertSame($hash, hash_file('sha256', dirname(__DIR__, 4).'/config/'.$file.'.json'));
        }
    }

    /** @return iterable<string, array{string, mixed, string}> */
    public static function invalidUpgradeFields(): iterable
    {
        yield 'unknown gameplay' => ['unknown', 1, 'unknown'];
        yield 'zero level' => ['nivel', 0, 'nivel'];
        yield 'float level' => ['nivel', 2.0, 'nivel'];
        yield 'blank description' => ['descricao', '', 'descricao'];
        yield 'missing modifiers' => ['modificadores', null, 'modificadores'];
        yield 'empty modifiers' => ['modificadores', [], 'modificadores'];
        yield 'wrong list' => ['modificadores', new stdClass, 'modificadores'];
        yield 'invalid provenance' => ['_fonte', 'invalid', '_fonte'];
        yield 'invalid note' => ['_nota', false, '_nota'];
    }

    #[DataProvider('invalidUpgradeFields')]
    public function test_upgrade_entry_validation_has_file_id_and_field(string $field, mixed $value, string $diagnostic): void
    {
        $root = self::raw('upgrades');
        self::upgradeEntry($root)->{$field} = $value;
        $this->assertInvalid(fn () => (new UpgradeDefinitionMapper(self::weapons()))->map($root, 'fixture.json'), 'bow', $diagnostic);
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function invalidEffects(): iterable
    {
        yield 'unknown attribute' => ['atributo', 'mana'];
        yield 'absent attribute' => ['atributo', 'raioExplosao'];
        yield 'unknown type' => ['tipo', 'MULTIPLY'];
        yield 'value string' => ['valor', '1'];
        yield 'value boolean' => ['valor', true];
        yield 'nan' => ['valor', NAN];
        yield 'infinity' => ['valor', INF];
        yield 'unknown field' => ['evil', 1];
    }

    #[DataProvider('invalidEffects')]
    public function test_modifier_data_is_strict(string $field, mixed $value): void
    {
        $root = self::raw('upgrades');
        $entry = self::upgradeEntry($root);
        $effect = ConfigSchema::object(ConfigSchema::list($entry->modificadores, 'test')[0], 'test');
        $effect->{$field} = $value;
        $this->assertInvalid(fn () => (new UpgradeDefinitionMapper(self::weapons()))->map($root, 'fixture.json'), 'bow', $field);
    }

    public function test_unknown_weapon_and_duplicate_upgrade_level_are_errors(): void
    {
        $root = self::raw('upgrades');
        ConfigSchema::object($root->upgrades, 'test')->unknown = [self::upgradeEntry($root)];
        $this->assertInvalid(fn () => (new UpgradeDefinitionMapper(self::weapons()))->map($root, 'fixture.json'), 'unknown', 'arma');
        $root = self::raw('upgrades');
        $entry = self::upgradeEntry($root);
        ConfigSchema::object($root->upgrades, 'test')->bow = [$entry, $entry];
        $this->assertInvalid(fn () => (new UpgradeDefinitionMapper(self::weapons()))->map($root, 'fixture.json'), 'bow', 'nivel');
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function invalidEvolutionFields(): iterable
    {
        yield 'unknown' => ['evil', 1];
        yield 'name' => ['nome', ''];
        yield 'id' => ['id', ''];
        yield 'requirements' => ['requer', []];
        yield 'result' => ['resultado', null];
        yield 'origin' => ['_fonte', 'unknown'];
    }

    #[DataProvider('invalidEvolutionFields')]
    public function test_evolution_entry_fields_are_validated(string $field, mixed $value): void
    {
        $root = self::raw('evolutions');
        self::evolutionEntry($root)->{$field} = $value;
        $this->assertInvalid(fn () => (new EvolutionDefinitionMapper(self::weapons(), ['sharpness', 'gunpowder']))->map($root, 'fixture.json'),
            $field === 'id' ? 'evolucoes' : 'enchanted_stone_sword', $field);
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function invalidResults(): iterable
    {
        yield 'replacement' => ['substituiArma', 'missing'];
        yield 'shape' => ['formato', 'pulso'];
        yield 'targeting' => ['mira', 'area_ao_redor'];
        yield 'unknown field' => ['explodeSempre', true];
        yield 'wrong optional field' => ['projeteis', null];
        yield 'damage' => ['dano', -1.0];
        yield 'cooldown type' => ['cooldownSegundos', '1'];
        yield 'finite' => ['alcance', INF];
        yield 'angle' => ['anguloGraus', 361];
    }

    #[DataProvider('invalidResults')]
    public function test_evolution_result_reuses_strict_weapon_mapping(string $field, mixed $value): void
    {
        $root = self::raw('evolutions');
        ConfigSchema::object(self::evolutionEntry($root)->resultado, 'test')->{$field} = $value;
        $this->assertInvalid(fn () => (new EvolutionDefinitionMapper(self::weapons(), ['sharpness', 'gunpowder']))->map($root, 'fixture.json'),
            'enchanted_stone_sword', $field === 'formato' ? 'anguloGraus' : $field);
    }

    public function test_evolution_reference_levels_and_duplicate_ids_are_validated(): void
    {
        foreach (['arma', 'passivo'] as $kind) {
            foreach (['id' => 'missing', 'nivelMinimo' => 0] as $field => $value) {
                $root = self::raw('evolutions');
                $required = ConfigSchema::object(self::evolutionEntry($root)->requer, 'test');
                ConfigSchema::object($required->{$kind}, 'test')->{$field} = $value;
                $this->assertInvalid(fn () => (new EvolutionDefinitionMapper(self::weapons(), ['sharpness', 'gunpowder']))->map($root, 'fixture.json'),
                    'enchanted_stone_sword', $field);
            }
        }
        $root = self::raw('evolutions');
        $entry = self::evolutionEntry($root);
        $root->evolucoes = [$entry, $entry];
        $this->assertInvalid(fn () => (new EvolutionDefinitionMapper(self::weapons(), ['sharpness']))->map($root, 'fixture.json'),
            'enchanted_stone_sword', 'id');
    }

    /** @return iterable<string, array{string, string, mixed}> */
    public static function invalidRoots(): iterable
    {
        foreach (['upgrades', 'evolutions'] as $file) {
            yield "$file filename" => [$file, 'arquivo', 'wrong.json'];
            yield "$file requirements" => [$file, 'requisitos', false];
            yield "$file invalid requirement" => [$file, 'requisitos', ['']];
            yield "$file note" => [$file, 'nota', null];
        }
    }

    #[DataProvider('invalidRoots')]
    public function test_metadata_is_validated(string $file, string $field, mixed $value): void
    {
        $root = self::raw($file);
        ConfigSchema::object($root->_meta, 'test')->{$field} = $value;
        $this->assertInvalid(fn () => $file === 'upgrades'
            ? (new UpgradeDefinitionMapper(self::weapons()))->map($root, 'fixture.json')
            : (new EvolutionDefinitionMapper(self::weapons(), ['sharpness', 'gunpowder']))->map($root, 'fixture.json'), '_meta', $field);
    }

    public function test_bad_roots_empty_collections_and_unknown_root_fields_fail(): void
    {
        foreach (['upgrades', 'evolutions'] as $file) {
            $loader = $file === 'upgrades' ? new UpgradeConfigLoader(self::weapons())
                : new EvolutionConfigLoader(self::weapons(), ['sharpness', 'gunpowder']);
            foreach (['null', '[]', '{}', '{"_meta":null}', '{"surpresa":true}'] as $json) {
                try {
                    $loader->decode($json, 'fixture.json');
                    self::fail('Raiz inválida deveria falhar.');
                } catch (InvalidArgumentException $error) {
                    self::assertStringContainsString('fixture.json', $error->getMessage());
                }
            }
        }
        $root = self::raw('upgrades');
        $root->upgrades = new stdClass;
        $this->assertInvalid(fn () => (new UpgradeDefinitionMapper(self::weapons()))->map($root, 'fixture.json'), 'upgrades', 'vazio');
        $root = self::raw('evolutions');
        $root->evolucoes = [];
        $this->assertInvalid(fn () => (new EvolutionDefinitionMapper(self::weapons(), []))->map($root, 'fixture.json'), 'evolucoes', 'vazio');
    }

    public function test_duplicate_evolution_combination_and_collision_with_base_id_are_rejected(): void
    {
        $root = self::raw('evolutions');
        $original = self::evolutionEntry($root);
        $duplicate = clone $original;
        $duplicate->id = 'other_evolution';
        $root->evolucoes = [$original, $duplicate];
        $this->assertInvalid(fn () => (new EvolutionDefinitionMapper(self::weapons(), ['sharpness']))->map($root, 'fixture.json'), 'other_evolution', 'requer');
        $root = self::raw('evolutions');
        self::evolutionEntry($root)->id = 'bow';
        $this->assertInvalid(fn () => (new EvolutionDefinitionMapper(self::weapons(), ['sharpness']))->map($root, 'fixture.json'), 'bow', 'id');
    }

    public function test_malformed_json_duplicate_keys_and_missing_files_fail(): void
    {
        foreach ([new UpgradeConfigLoader(self::weapons()), new EvolutionConfigLoader(self::weapons(), [])] as $loader) {
            foreach (['{', '{"bow":1,"bow":2}', '{"bow":1,"b\\u006fw":2}'] as $json) {
                try {
                    $loader->decode($json, 'fixture.json');
                    self::fail('JSON inválido deveria falhar.');
                } catch (InvalidArgumentException $error) {
                    self::assertStringContainsString('fixture.json', $error->getMessage());
                }
            }
            try {
                $loader->load('missing-a8-fixture.json');
                self::fail('Arquivo inexistente deveria falhar.');
            } catch (InvalidArgumentException $error) {
                self::assertStringContainsString('missing-a8-fixture.json', $error->getMessage());
            }
        }
    }

    private static function weapons(): WeaponCatalog
    {
        return (new WeaponConfigLoader)->load(dirname(__DIR__, 4).'/config/weapons.json');
    }

    private static function raw(string $file): stdClass
    {
        return ConfigSchema::object(ConfigJsonReader::load(dirname(__DIR__, 4).'/config/'.$file.'.json'), 'test');
    }

    private static function upgradeEntry(stdClass $root): stdClass
    {
        return ConfigSchema::object(ConfigSchema::list(ConfigSchema::object($root->upgrades, 'test')->bow, 'test')[0], 'test');
    }

    private static function evolutionEntry(stdClass $root): stdClass
    {
        return ConfigSchema::object(ConfigSchema::list($root->evolucoes, 'test')[0], 'test');
    }

    /** @param callable(): mixed $action */
    private function assertInvalid(callable $action, string $id, string $field): void
    {
        try {
            $action();
            self::fail('Configuração inválida deveria falhar.');
        } catch (InvalidArgumentException $error) {
            self::assertStringContainsString('fixture.json', $error->getMessage());
            self::assertStringContainsString($id, $error->getMessage());
            self::assertStringContainsString($field, $error->getMessage());
        }
    }
}
