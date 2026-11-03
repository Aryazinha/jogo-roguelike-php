<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Domain\Score;

use InvalidArgumentException;
use Jogo\Domain\Score\PlayerName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PlayerNameTest extends TestCase
{
    public function test_spaces_are_trimmed_and_collapsed(): void
    {
        self::assertSame('Maria Carolina', (new PlayerName("  Maria \t  Carolina "))->value);
    }

    public function test_accented_letters_count_as_one_character(): void
    {
        self::assertSame(str_repeat('é', 20), (new PlayerName(str_repeat('é', 20)))->value);
        self::assertSame('João Ícaro', (new PlayerName('João Ícaro'))->value);

        $this->expectException(InvalidArgumentException::class);
        new PlayerName(str_repeat('é', 21));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidNames(): iterable
    {
        yield 'vazio' => [''];
        yield 'so espacos' => ['    '];
        yield 'longo demais' => [str_repeat('a', 21)];
        yield 'caractere de controle' => ["Carol\x07"];
        yield 'utf8 invalido' => ["Carol\xFF"];
    }

    #[DataProvider('invalidNames')]
    public function test_invalid_names_are_rejected(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PlayerName($name);
    }
}
