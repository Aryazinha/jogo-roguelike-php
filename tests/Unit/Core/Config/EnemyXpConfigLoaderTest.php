<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Core\Config;

use InvalidArgumentException;
use Jogo\Core\Config\EnemyXpConfigLoader;
use PHPUnit\Framework\TestCase;

final class EnemyXpConfigLoaderTest extends TestCase
{
    public function test_real_file_provides_the_xp_range_of_every_enemy(): void
    {
        $table = (new EnemyXpConfigLoader)->load(dirname(__DIR__, 4).'/config/enemies.json');

        $expected = ['zombie' => [3, 4], 'spider' => [2, 3], 'husk' => [7, 10], 'slime' => [4, 6],
            'slime_pequeno' => [1, 2], 'skeleton' => [5, 7], 'warden' => [150, 150]];
        foreach ($expected as $id => [$min, $max]) {
            self::assertSame([$min, $max], [$table->get($id)->min, $table->get($id)->max], $id);
        }
        self::assertFalse($table->has('creeper'));
    }

    public function test_missing_xp_is_reported_with_enemy_and_field(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('enemies.json: zombie.xp deve ser um objeto.');
        (new EnemyXpConfigLoader)->decode('{"comuns":{"zombie":{"vida":13}}}');
    }

    public function test_inverted_range_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('enemies.json: zombie.xp.max deve ser maior ou igual a min.');
        (new EnemyXpConfigLoader)->decode('{"comuns":{"zombie":{"xp":{"min":5,"max":3}}}}');
    }

    public function test_fractional_xp_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('enemies.json: spider.xp.min deve ser um inteiro >= 0.');
        (new EnemyXpConfigLoader)->decode('{"comuns":{"spider":{"xp":{"min":2.5,"max":3}}}}');
    }

    public function test_same_enemy_in_two_groups_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('enemies.json: slime aparece em mais de um grupo.');
        (new EnemyXpConfigLoader)->decode('{"comuns":{"slime":{"xp":{"min":1,"max":2}}},"especiais":{"slime":{"xp":{"min":1,"max":2}}}}');
    }
}
