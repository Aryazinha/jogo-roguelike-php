<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Core\Config;

use InvalidArgumentException;
use Jogo\Core\Config\PassiveConfigLoader;
use Jogo\Domain\Value\ModifierType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PassiveConfigLoaderTest extends TestCase
{
    public function test_real_file_maps_the_six_passive_items(): void
    {
        $path = dirname(__DIR__, 4).'/config/passives.json';
        $before = file_get_contents($path);

        $catalog = (new PassiveConfigLoader)->load($path);

        self::assertSame(['sharpness', 'gunpowder', 'feather', 'golden_apple', 'clock', 'magnet'], $catalog->ids());
        $expected = [
            'sharpness' => ['dano', ModifierType::PERCENT, 0.08],
            'gunpowder' => ['raioExplosao', ModifierType::PERCENT, 0.1],
            'feather' => ['velocidadeJogador', ModifierType::PERCENT, 0.06],
            'golden_apple' => ['vidaMaxima', ModifierType::FLAT, 2.0],
            'clock' => ['cooldown', ModifierType::PERCENT, -0.05],
            'magnet' => ['raioColeta', ModifierType::PERCENT, 0.15],
        ];
        foreach ($expected as $id => [$attribute, $type, $value]) {
            $definition = $catalog->get($id);
            self::assertSame(5, $definition->maxLevel);
            self::assertCount(1, $definition->modifiersPerLevel);
            $template = $definition->modifiersPerLevel[0];
            self::assertSame([$attribute, $type, $value], [$template->attribute, $template->type, $template->value]);
        }
        self::assertSame($before, file_get_contents($path));
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidDocuments(): iterable
    {
        $item = static fn (string $body): string => '{"_meta":{"arquivo":"passives.json","requisitos":["RF21"],"nota":"n"},"itens":{"totem":'.$body.'}}';
        yield 'campo desconhecido' => [$item('{"nome":"T","descricao":"d","niveisMaximos":5,"raridade":1,"modificadoresPorNivel":[{"atributo":"dano","tipo":"FLAT","valor":1}]}'),
            'passives.json: totem.raridade é um campo de gameplay desconhecido.'];
        yield 'nivel maximo zero' => [$item('{"nome":"T","descricao":"d","niveisMaximos":0,"modificadoresPorNivel":[{"atributo":"dano","tipo":"FLAT","valor":1}]}'),
            'passives.json: totem.niveisMaximos deve ser um inteiro >= 1.'];
        yield 'atributo desconhecido' => [$item('{"nome":"T","descricao":"d","niveisMaximos":5,"modificadoresPorNivel":[{"atributo":"sorte","tipo":"FLAT","valor":1}]}'),
            'passives.json: totem.modificadoresPorNivel[0]: Atributo desconhecido: sorte.'];
        yield 'tipo invalido' => [$item('{"nome":"T","descricao":"d","niveisMaximos":5,"modificadoresPorNivel":[{"atributo":"dano","tipo":"MULT","valor":1}]}'),
            'passives.json: totem.modificadoresPorNivel[0].tipo deve ser FLAT ou PERCENT.'];
        yield 'sem modificadores' => [$item('{"nome":"T","descricao":"d","niveisMaximos":5,"modificadoresPorNivel":[]}'),
            'passives.json: totem.modificadoresPorNivel não pode estar vazio.'];
    }

    #[DataProvider('invalidDocuments')]
    public function test_invalid_configuration_names_file_item_and_field(string $json, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        (new PassiveConfigLoader)->decode($json);
    }
}
