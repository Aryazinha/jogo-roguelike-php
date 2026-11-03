<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Core\Config;

use InvalidArgumentException;
use Jogo\Core\Config\CharacterConfigLoader;
use Jogo\Core\Config\WeaponConfigLoader;
use Jogo\Domain\Character\CharacterDefinition;
use Jogo\Domain\Value\ModifierType;
use Jogo\Domain\Weapon\WeaponCatalog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class CharacterConfigLoaderTest extends TestCase
{
    public function test_real_file_maps_steve_and_alex_with_the_values_of_the_requirements(): void
    {
        $path = self::configDir().'/characters.json';
        $before = file_get_contents($path);
        self::assertIsString($before);

        $catalog = (new CharacterConfigLoader(self::weapons()))->load($path);

        self::assertSame(2, $catalog->count());
        self::assertSame(['steve', 'alex'], array_map(static fn (CharacterDefinition $c): string => $c->id, $catalog->all()));

        $steve = $catalog->get('steve');
        self::assertSame(['Steve', 20.0, 1.0, 30.0, 'stone_sword'], [$steve->name, $steve->maxHealth,
            $steve->regenerationHp, $steve->regenerationIntervalSeconds, $steve->startingWeaponId]);
        self::assertSame(['encantamento_de_dano', 5, 'dano', ModifierType::PERCENT, 0.1], [$steve->passive->id,
            $steve->passive->everyLevels, $steve->passive->attribute, $steve->passive->type, $steve->passive->value]);

        $alex = $catalog->get('alex');
        self::assertSame(['Alex', 16.0, 1.0, 15.0, 'bow'], [$alex->name, $alex->maxHealth,
            $alex->regenerationHp, $alex->regenerationIntervalSeconds, $alex->startingWeaponId]);
        self::assertSame(['reducao_de_cooldown', 4, 'cooldown', ModifierType::FLAT, -0.2], [$alex->passive->id,
            $alex->passive->everyLevels, $alex->passive->attribute, $alex->passive->type, $alex->passive->value]);

        self::assertSame($before, file_get_contents($path));
    }

    /** @return iterable<string, array{callable(stdClass): void, string}> */
    public static function invalidDocuments(): iterable
    {
        yield 'campo desconhecido' => [static function (stdClass $root): void {
            $root->personagens->steve->velocidade = 100;
        }, 'characters.json: steve.velocidade é um campo de gameplay desconhecido.'];
        yield 'vida ausente' => [static function (stdClass $root): void {
            unset($root->personagens->steve->vida);
        }, 'characters.json: steve.vida deve ser um número finito.'];
        yield 'vida zero' => [static function (stdClass $root): void {
            $root->personagens->alex->vida = 0;
        }, 'characters.json: alex.vida deve ser maior que zero.'];
        yield 'vida texto' => [static function (stdClass $root): void {
            $root->personagens->alex->vida = '16';
        }, 'characters.json: alex.vida deve ser um número finito.'];
        yield 'regeneracao negativa' => [static function (stdClass $root): void {
            $root->personagens->steve->regeneracao->hp = -1;
        }, 'characters.json: steve.regeneracao.hp deve ser maior que zero.'];
        yield 'intervalo zero' => [static function (stdClass $root): void {
            $root->personagens->alex->regeneracao->intervaloSegundos = 0;
        }, 'characters.json: alex.regeneracao.intervaloSegundos deve ser maior que zero.'];
        yield 'campo extra na regeneracao' => [static function (stdClass $root): void {
            $root->personagens->steve->regeneracao->bonus = 1;
        }, 'characters.json: steve.regeneracao.bonus é um campo de gameplay desconhecido.'];
        yield 'arma inexistente' => [static function (stdClass $root): void {
            $root->personagens->steve->armaInicial = 'diamond_sword';
        }, 'characters.json: steve.armaInicial referencia a arma inexistente diamond_sword.'];
        yield 'nome vazio' => [static function (stdClass $root): void {
            $root->personagens->alex->nome = ' ';
        }, 'characters.json: alex.nome deve ser uma string não vazia.'];
        yield 'passiva ausente' => [static function (stdClass $root): void {
            unset($root->personagens->alex->passiva);
        }, 'characters.json: alex.passiva deve ser um objeto.'];
        yield 'intervalo de niveis zero' => [static function (stdClass $root): void {
            $root->personagens->steve->passiva->aCadaNiveis = 0;
        }, 'characters.json: steve.passiva.aCadaNiveis deve ser um inteiro >= 1.'];
        yield 'intervalo de niveis fracionario' => [static function (stdClass $root): void {
            $root->personagens->alex->passiva->aCadaNiveis = 4.5;
        }, 'characters.json: alex.passiva.aCadaNiveis deve ser um inteiro >= 1.'];
        yield 'tipo de modificador invalido' => [static function (stdClass $root): void {
            $root->personagens->alex->passiva->modificador->tipo = 'MULT';
        }, 'characters.json: alex.passiva.modificador.tipo deve ser FLAT ou PERCENT.'];
        yield 'atributo desconhecido' => [static function (stdClass $root): void {
            $root->personagens->steve->passiva->modificador->atributo = 'sorte';
        }, 'characters.json: steve.passiva.modificador: Atributo desconhecido: sorte.'];
        yield 'valor zero' => [static function (stdClass $root): void {
            $root->personagens->steve->passiva->modificador->valor = 0;
        }, 'characters.json: steve.passiva.modificador: O valor da passiva deve ser finito e diferente de zero.'];
        yield 'passivas repetidas' => [static function (stdClass $root): void {
            $root->personagens->alex->passiva->id = 'encantamento_de_dano';
        }, 'A passiva encantamento_de_dano deve ser única por personagem.'];
        yield 'fonte invalida' => [static function (stdClass $root): void {
            $root->personagens->steve->_fonte = 'inventado';
        }, 'characters.json: steve._fonte deve ser enunciado ou provisorio.'];
        yield 'sem personagens' => [static function (stdClass $root): void {
            $root->personagens = new stdClass;
        }, 'characters.json: personagens não pode estar vazio.'];
        yield 'arquivo errado no meta' => [static function (stdClass $root): void {
            $root->_meta->arquivo = 'weapons.json';
        }, 'characters.json: _meta.arquivo deve ser characters.json.'];
    }

    /** @param callable(stdClass): void $mutate */
    #[DataProvider('invalidDocuments')]
    public function test_invalid_configuration_fails_with_file_character_and_field(callable $mutate, string $message): void
    {
        $root = self::document();
        $mutate($root);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        (new CharacterConfigLoader(self::weapons()))->decode(json_encode($root, JSON_THROW_ON_ERROR));
    }

    public function test_duplicate_character_key_is_rejected_before_mapping(): void
    {
        $json = '{"_meta":{"arquivo":"characters.json","requisitos":[],"nota":"n"},"personagens":{"steve":{},"steve":{}}}';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('personagens.steve contém chave/ID duplicado');
        (new CharacterConfigLoader(self::weapons()))->decode($json);
    }

    public function test_missing_file_is_reported_with_its_path(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('inexistente.json: arquivo inexistente');
        (new CharacterConfigLoader(self::weapons()))->load('inexistente.json');
    }

    private static function document(): stdClass
    {
        $json = file_get_contents(self::configDir().'/characters.json');
        self::assertIsString($json);
        $root = json_decode($json, flags: JSON_THROW_ON_ERROR);
        self::assertInstanceOf(stdClass::class, $root);

        return $root;
    }

    private static function weapons(): WeaponCatalog
    {
        return (new WeaponConfigLoader)->load(self::configDir().'/weapons.json');
    }

    private static function configDir(): string
    {
        return dirname(__DIR__, 4).'/config';
    }
}
